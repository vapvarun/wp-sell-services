#!/usr/bin/env python3
"""Docs consistency gate for WP Sell Services (free + Pro).

Catches the defect classes that made the 1.3.0 docs untrustworthy:

  1. docs_config.json out of sync with the files on disk (orphans / missing)
  2. Broken image references
  3. Broken internal .md links
  4. Hooks documented but never fired (a dead add_action a developer will bind to)
  5. Admin paths naming a Settings tab that does not exist
  6. Duplicate publishing from the Pro docs tree

Run from anywhere:

    python3 bin/docs-audit.py            # report, exit 1 on any failure
    python3 bin/docs-audit.py --quiet    # only print failures

The Pro plugin is expected as a sibling directory; if it is missing, the Pro
checks are skipped rather than failing.
"""
import json
import os
import re
import sys

QUIET = "--quiet" in sys.argv
FREE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PRO = os.path.join(os.path.dirname(FREE), "wp-sell-services-pro")
DOCS = os.path.join(FREE, "docs", "website")

# The Settings tabs that actually exist (src/Admin/Settings.php::init_tabs,
# plus Branding added by Pro). Anything else in a "Settings > X" path is stale.
VALID_TABS = {
    "General", "Pages", "Payment Gateways", "Commission & Tax", "Payouts",
    "Vendors", "Orders & Disputes", "Emails", "Advanced", "Branding",
}
# Other products' settings screens - not ours to validate. Covers both
# "WooCommerce > Settings > X" and "Settings > X in the Razorpay Dashboard",
# where the third-party product is named anywhere on the line.
FOREIGN = re.compile(
    r"(WooCommerce|Downloads|FluentCart|SureCart) > Settings"
    r"|Settings > (Permalinks|General\b.*Anyone can register)"
    r"|(Razorpay|Stripe|PayPal|WordPress|Google|Apple) Dashboard"
    r"|Dashboard > (Developers|Settings)"
)

failures, notes = [], []


def fail(check, msg):
    failures.append((check, msg))


def ok(check, msg):
    if not QUIET:
        notes.append((check, msg))


def md_files(root):
    for r, _, fs in os.walk(root):
        if "images" in r:
            continue
        for n in sorted(fs):
            if n.endswith(".md"):
                yield os.path.join(r, n)


def hooks_in_php(*roots):
    """Every hook name fired by the plugin, including multi-line do_action()."""
    pat = re.compile(r"(?:do_action|apply_filters)\s*\(\s*(?:\n\s*)?'([a-z0-9_]+)'")
    found = set()
    for root in roots:
        if not os.path.isdir(root):
            continue
        for r, _, fs in os.walk(root):
            if "vendor" in r or "node_modules" in r or "/dist/" in r:
                continue
            for n in fs:
                if n.endswith(".php"):
                    with open(os.path.join(r, n), errors="ignore") as fh:
                        found |= set(pat.findall(fh.read()))
    return found


# --- 1. config <-> disk -------------------------------------------------------
cfg_path = os.path.join(DOCS, "docs_config.json")
cfg = json.load(open(cfg_path))
listed = {d["file"] for cat in cfg["categories"] for d in cat["docs"]}
on_disk = {os.path.relpath(p, DOCS) for p in md_files(DOCS)}

for missing in sorted(listed - on_disk):
    fail("config", f"docs_config.json lists a file that does not exist: {missing}")
for orphan in sorted(on_disk - listed):
    fail("config", f"page on disk but not in docs_config.json (will not publish): {orphan}")
if listed == on_disk:
    ok("config", f"docs_config.json is 1:1 with disk ({len(listed)} pages)")

# --- 2 & 3. broken images and links ------------------------------------------
img_re = re.compile(r"!\[[^\]]*\]\(([^)\s]+)")
link_re = re.compile(r"(?<!!)\[[^\]]*\]\(([^)\s#]+\.md)")
bad_img = bad_link = 0
for p in md_files(DOCS):
    body = open(p).read()
    rel = os.path.relpath(p, DOCS)
    for m in img_re.finditer(body):
        if not os.path.exists(os.path.normpath(os.path.join(os.path.dirname(p), m.group(1)))):
            fail("images", f"{rel} -> {m.group(1)}")
            bad_img += 1
    for m in link_re.finditer(body):
        if not os.path.exists(os.path.normpath(os.path.join(os.path.dirname(p), m.group(1)))):
            fail("links", f"{rel} -> {m.group(1)}")
            bad_link += 1
if not bad_img:
    ok("images", "no broken image references")
if not bad_link:
    ok("links", "no broken internal links")

# --- 4. phantom hooks ---------------------------------------------------------
fired = hooks_in_php(os.path.join(FREE, "src"), os.path.join(FREE, "templates"),
                     os.path.join(FREE, "includes"), os.path.join(PRO, "src"),
                     os.path.join(PRO, "templates"))
hooks_doc = os.path.join(DOCS, "developer-guide", "hooks-filters.md")
if fired and os.path.exists(hooks_doc):
    text = open(hooks_doc).read()
    # Only rows of a reference table assert "this hook exists". Prose that
    # explicitly flags a removed name is allowed to mention it.
    documented = set(re.findall(r"^\|\s*`(wpss_[a-z0-9_]+)`", text, re.M))
    dynamic = re.compile(r"_\{|\{status\}")
    phantom = sorted(h for h in documented - fired if not dynamic.search(h))
    for h in phantom:
        fail("hooks", f"documented in a table but never fired: {h}")
    if not phantom:
        ok("hooks", f"all {len(documented)} tabled hooks are fired in source")

# --- 5. stale admin paths -----------------------------------------------------
path_re = re.compile(r"Settings > ([A-Z][A-Za-z& ]*?)(?=\*\*|\.|,|:|<|\||$| and | to | for | in )")
stale = 0
for p in md_files(DOCS):
    rel = os.path.relpath(p, DOCS)
    for i, line in enumerate(open(p), 1):
        if FOREIGN.search(line):
            continue
        for m in path_re.finditer(line):
            tab = m.group(1).strip()
            if tab and tab not in VALID_TABS:
                fail("admin-paths", f"{rel}:{i} names a Settings tab that does not exist: '{tab}'")
                stale += 1
if not stale:
    ok("admin-paths", "every 'Settings > X' path names a real tab")

# --- 6. UI label drift --------------------------------------------------------
# Left column: names that appeared in docs but are not what the UI says.
# Right column: the string the plugin actually renders.
WRONG_LABELS = {
    "Dashboard > My Requests": "Dashboard > Buyer Requests",
    "Wallet & Earnings": "Earnings & Payouts",
    "Delivery Submitted": "Pending Approval (status) / 'delivery submitted' (the notification)",
    "Recurring Subscriptions page": "the Subscriptions page",
}
drift = 0
for p in md_files(DOCS):
    rel = os.path.relpath(p, DOCS)
    for i, line in enumerate(open(p), 1):
        for wrong, right in WRONG_LABELS.items():
            if wrong in line:
                fail("labels", f"{rel}:{i} says '{wrong}' -- the UI says '{right}'")
                drift += 1
if not drift:
    ok("labels", "doc labels match the strings the plugin renders")

# --- 7. Pro tree must not publish --------------------------------------------
pro_cfg = os.path.join(PRO, "docs", "website", "docs_config.json")
if os.path.exists(pro_cfg):
    pc = json.load(open(pro_cfg))
    if pc.get("categories"):
        fail("pro-tree", "wp-sell-services-pro/docs/website/docs_config.json still publishes pages; "
                         "Pro docs belong in the free tree marked [PRO]")
    else:
        ok("pro-tree", "Pro docs manifest is retired (publishes nothing)")

# --- report -------------------------------------------------------------------
for check, msg in notes:
    print(f"  PASS  [{check}] {msg}")
if failures:
    print()
    for check, msg in failures:
        print(f"  FAIL  [{check}] {msg}")
    print(f"\n{len(failures)} problem(s) found.")
    sys.exit(1)
print("\nDocs audit passed.")
