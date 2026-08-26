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

# Admin-path checks run over the WHOLE docs tree, not just the published one.
# docs/architecture/ and docs/qa/ are read by the people most likely to act on
# a wrong breadcrumb, and a stale tab name there rots exactly the same way -
# which is how "Settings > Payments" survived the July settings regroup in
# MONEY-FLOW.md and the QA checklist while docs/website/ passed clean.
ADMIN_PATH_ROOTS = [
    os.path.join(FREE, "docs", "website"),
    os.path.join(FREE, "docs", "architecture"),
    os.path.join(FREE, "docs", "qa"),
    os.path.join(FREE, "docs", "decisions"),
]

# The Settings tabs that actually exist (src/Admin/Settings.php::init_tabs,
# plus Branding added by Pro). Anything else in a "Settings > X" path is stale.
VALID_TABS = {
    "General", "Pages", "Payment Gateways", "Commission & Tax", "Payouts",
    "Vendors", "Orders & Disputes", "Emails", "Advanced", "Branding",
}

# Tab names retired by the July 2026 settings regroup. They read as plausible,
# so they are called out by name rather than lumped into "not a real tab" -
# each one has a specific successor and the message should say which.
RETIRED_TABS = {
    "Payments": "Payment Gateways (gateways) / Payouts (withdrawals, clearance)",
    "Vendor": "Vendors",
    "Orders": "Orders & Disputes",
    "Gateways": "Payment Gateways",
    "Commission": "Commission & Tax",
    "License": "not a Settings tab - License is a top-level page, 'Sell Services > License'",
    "Notifications": "Emails",
}

# Top-level items under the "Sell Services" admin menu (src/Admin/Admin.php,
# src/Admin/Pages/*, plus the Pro-only ones). These are MENU titles, which are
# not always the page title - the service queue's menu label is "Moderation"
# even though its page heading says "Service Moderation".
VALID_MENU_ITEMS = {
    "Dashboard", "Setup Wizard", "All Services", "Add New Service",
    "Buyer Requests", "Add New Request", "Categories", "Tags",
    "Moderation", "Review Moderation", "Disputes", "Vendors", "Orders",
    "Create Order", "Subscriptions", "Withdrawals", "Analytics",
    "Audit Log", "My Notifications", "Settings", "License", "Upgrade to Pro",
}

# Menu labels that used to exist, or that docs keep inventing.
RETIRED_MENU_ITEMS = {
    "Reviews": "Review Moderation",
    "Services": "All Services (browse) / Moderation (the pending queue)",
    "System": "no such menu - the log viewer is 'Sell Services > Audit Log'",
    "Logs": "Audit Log",
    "Payouts": "Withdrawals (the payout queue) / Settings > Payouts (the rules)",
    "Earnings": "Withdrawals",
}

# Other products' settings screens - not ours to validate. Covers both
# "WooCommerce > Settings > X" and "Settings > X in the Razorpay Dashboard",
# where the third-party product is named anywhere on the line.
FOREIGN = re.compile(
    r"(WooCommerce|Downloads|FluentCart|SureCart) [>→] Settings"
    r"|Settings [>→] (Permalinks|General\b.*Anyone can register)"
    r"|(Razorpay|Stripe|PayPal|WordPress|Google|Apple) Dashboard"
    r"|Dashboard [>→] (Developers|Settings)"
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
# Relative .md links only -- an http(s) target is an external link, not a file
# this checker can resolve.
link_re = re.compile(r"(?<!!)\[[^\]]*\]\((?!https?://)([^)\s#]+\.md)")
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

# --- 3b. links must not escape the published tree ----------------------------
# `../../architecture/FOO.md` resolves on disk but only docs/website/ publishes,
# so it 404s on the docs site. Link to the repo URL instead.
escaped = 0
for p in md_files(DOCS):
    rel = os.path.relpath(p, DOCS)
    for i, line in enumerate(open(p), 1):
        for m in re.finditer(r"\]\((\.\./\.\./[^)]+)\)", line):
            fail("scope", f"{rel}:{i} links outside docs/website/: {m.group(1)} "
                          f"-- use the repo URL, that path does not publish")
            escaped += 1
if not escaped:
    ok("scope", "no page links outside the published tree")

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

# --- 4b. hook citations point at the right place ------------------------------
# The reference table cites `file.php:NNN` beside each hook. Line numbers move on
# every edit and the 6,187-line functions.php was split into src/functions/*.php
# in db27f79, so 21 rows still cited a file that is now a 43-line loader - the
# citation was decorative long before anyone noticed.
#
# This does NOT regenerate the page. The prose around these tables carries real
# judgement - which hooks are behavioural, which are template slots, which are
# internal and may change - and a generator would flatten all of it. It only
# asserts that a citation, where one is given, resolves to a line that really
# fires that hook.
def hook_line_index(*roots):
    """hook name -> set of "relpath:line" where it is fired."""
    pat = re.compile(r"(?:do_action|apply_filters)\s*\(\s*(?:\n\s*)?'([a-z0-9_]+)'")
    index = {}
    for root in roots:
        if not os.path.isdir(root):
            continue
        base = os.path.dirname(root)
        for r, _, fs in os.walk(root):
            if "vendor" in r or "node_modules" in r or "/dist/" in r:
                continue
            for n in fs:
                if not n.endswith(".php"):
                    continue
                path = os.path.join(r, n)
                rel = os.path.relpath(path, base)
                try:
                    lines = open(path, errors="ignore").read().splitlines()
                except OSError:
                    continue
                for i, line in enumerate(lines, 1):
                    # Multi-line do_action(): the name may sit on the next line.
                    window = line
                    if i < len(lines):
                        window = line + "\n" + lines[i]
                    for hook in pat.findall(window):
                        index.setdefault(hook, set()).add(f"{rel}:{i}")
    return index


if os.path.exists(hooks_doc):
    cite_re = re.compile(
        r"^\|\s*`(wpss_[a-z0-9_]+)`[^|]*\|[^|]*\|\s*`([A-Za-z0-9_/.-]+\.php):(\d+)`",
        re.M,
    )
    index = hook_line_index(
        os.path.join(FREE, "src"), os.path.join(FREE, "templates"),
        os.path.join(PRO, "src"), os.path.join(PRO, "templates"),
    )
    text = open(hooks_doc).read()
    cited = list(cite_re.finditer(text))
    stale = []

    for m in cited:
        hook, cited_file, cited_line = m.group(1), m.group(2), m.group(3)
        real = index.get(hook, set())

        if not real:
            continue  # phantom hooks are check 4's job, not this one.

        # A citation is right when SOME firing site ends with the cited
        # file:line. Paths in the doc are written relative to the plugin root
        # with the src/ prefix dropped on some rows, so match on the suffix.
        if any(site.endswith(f"{cited_file}:{cited_line}") or site.endswith(f"/{cited_file}:{cited_line}")
               for site in real):
            continue

        stale.append((hook, f"{cited_file}:{cited_line}", sorted(real)[0]))

    for hook, was, now in stale:
        fail("hook-citations", f"{hook} cites {was}; it is fired at {now}")

    if not stale:
        ok("hook-citations", f"all {len(cited)} hook citations resolve to a real firing site")


# --- 5. stale admin paths -----------------------------------------------------
# Both separators are in use across the docs: "Settings > X" in the customer
# pages, "Settings -> X" (an arrow) in the architecture and QA notes. Checking
# only one of them is how half these refs went stale unnoticed.
SEP = r"[>→]"
TERM = r"(?=\*\*|\.|,|:|<|\||\)|$| and | to | for | in |" + SEP + r")"
tab_re = re.compile(r"Settings\s*" + SEP + r"\s*([A-Z][A-Za-z& ]*?)" + TERM)
menu_re = re.compile(r"Sell Services\s*" + SEP + r"\s*([A-Z][A-Za-z&' ]*?)" + TERM)


def audit_admin_paths():
    stale = 0
    scanned = 0
    for root in ADMIN_PATH_ROOTS:
        if not os.path.isdir(root):
            continue
        for p in md_files(root):
            scanned += 1
            rel = os.path.relpath(p, FREE)
            for i, line in enumerate(open(p, errors="ignore"), 1):
                if FOREIGN.search(line):
                    continue

                for m in tab_re.finditer(line):
                    tab = m.group(1).strip()
                    if not tab or tab in VALID_TABS:
                        continue
                    if tab in RETIRED_TABS:
                        fail("admin-paths",
                             f"{rel}:{i} names the RETIRED Settings tab '{tab}' "
                             f"-- it is now {RETIRED_TABS[tab]}")
                    else:
                        fail("admin-paths",
                             f"{rel}:{i} names a Settings tab that does not exist: '{tab}'")
                    stale += 1

                for m in menu_re.finditer(line):
                    item = m.group(1).strip()
                    if not item or item in VALID_MENU_ITEMS:
                        continue
                    if item in RETIRED_MENU_ITEMS:
                        fail("admin-paths",
                             f"{rel}:{i} names the RETIRED menu item "
                             f"'Sell Services > {item}' -- it is now "
                             f"{RETIRED_MENU_ITEMS[item]}")
                    else:
                        fail("admin-paths",
                             f"{rel}:{i} names a 'Sell Services' menu item that "
                             f"does not exist: '{item}'")
                    stale += 1
    return stale, scanned


stale, scanned = audit_admin_paths()
if not stale:
    ok("admin-paths",
       f"every 'Settings > X' tab and 'Sell Services > X' menu path is real "
       f"({scanned} pages across docs/website, docs/architecture, docs/qa, docs/decisions)")

# --- 6. UI label drift --------------------------------------------------------
# Left column: names that appeared in docs but are not what the UI says.
# Right column: the string the plugin actually renders.
WRONG_LABELS = {
    "Dashboard > My Requests": "Dashboard > Buyer Requests",
    "Wallet & Earnings": "Earnings & Payouts",
    # "Delivery Submitted" was on this list and should not have been: Settings.php
    # renders exactly that string as the label for the delivery_submitted
    # notification type, so a docs page naming it is right. The entry banned a
    # label the plugin itself shows, and email-types.md failed this gate for it.
    # If the STATUS ever gets described that way, catch it with the status name
    # rather than a substring that also matches the notification.
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
