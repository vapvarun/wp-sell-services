#!/usr/bin/env python3
"""
Feature x REST parity report — is every catalogued feature reachable by a client?

WHY THIS EXISTS
A mobile app can only render what the REST API exposes. The feature catalog says
what ships and in which tier; the API says what a client can reach. Nothing joined
them, so "ships on web" was being read as "the app can build it" — which is how an
app team discovers a gap halfway through a sprint instead of during planning.

WHY IT READS THE REPO, NOT A LIVE SITE
Curling /wp-json bakes one install's configuration into the answer: whichever
adapter, tier and plugin set that site happens to run. This parses the checkout, so
it gives the same answer on any machine, in CI, and for a plugin nobody has
installed yet. That is the difference between a report and a standard.

PORTABILITY
Nothing here is WP Sell Services specific except the values in CONFIG. Point it at
another Wbcom plugin pair by changing those.

USAGE
    python3 bin/app-parity.py                 # human-readable report
    python3 bin/app-parity.py --json          # machine-readable
    python3 bin/app-parity.py --check         # exit 1 if any feature has no route
"""

import json
import os
import re
import sys

CONFIG = {
    # Repo-relative; free is the anchor, pro is resolved as a sibling.
    "free_dir": os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
    "pro_dirname": "wp-sell-services-pro",
    "catalog": "docs/website/feature-catalog.md",
    "api_globs": ["src/API", "src/Frontend"],
    # Feature rows whose delivery is intentionally not an API surface. Listing
    # them here is a claim someone can argue with, which is the point — silence
    # would let a real gap hide among the deliberate ones.
    "not_api_surfaces": {
        "gutenberg blocks and shortcodes": "Server-rendered web surface; an app builds native screens instead.",
        "dark mode and theme integration": "Web CSS concern.",
        "frontend dashboard (buyer + vendor)": "The app IS the dashboard; parity is per-section, not per-page.",
        "role-based dashboard sections": "Same as above.",
        "admin order, vendor, dispute, withdrawal management": "Admin-only; the app spec excludes admin (App card: 'admin never').",
        "moderation queues": "Admin-only.",
        "wp-cli": "Operator tooling.",
        "rest api": "This is the mechanism, not a feature delivered through it.",
        "audit log": "Admin-only surface.",
        "ecommerce integrations": "Server-side payment rails; the app hands off to web checkout.",
        "file storage": "Transport detail behind media endpoints.",
        "standalone checkout (no other plugin needed)": "Web checkout handoff by design.",
        "payment gateways": "Reached via the checkout handoff, not a client-rendered screen.",
    },
    # Feature keyword -> the FIRST PATH SEGMENT of routes that serve it. Declared
    # once; the script verifies each base still resolves, so drift surfaces as a
    # failure rather than as a stale document nobody re-read.
    #
    # Only real bases belong here. Speculative aliases ("tips", "refund", "stats" -
    # endpoints that sound plausible but were never registered) make the staleness
    # check cry wolf, and a check that always warns gets ignored, which is how the
    # rot it exists to catch gets through.
    "feature_routes": {
        "service listings": ["services"],
        "vendor profiles": ["vendors", "portfolio"],
        "buyer requests": ["buyer-requests", "proposals"],
        "order lifecycle": ["orders"],
        "order messaging": ["conversations"],
        "reviews and ratings": ["reviews"],
        "disputes": ["disputes"],
        "favourites": ["favorites"],
        "services per vendor": ["services"],
        "commission": ["earnings", "commission-rules"],
        "vendor wallet": ["wallet", "withdrawals"],
        "manual payouts": ["withdrawals"],
        "stripe connect": ["stripe-connect"],
        "paypal payouts": ["paypal-payouts"],
        "tips": ["orders"],
        "paid extensions": ["extensions", "orders"],
        "milestone": ["milestones"],
        "refunds": ["orders"],
        "display currency": ["settings"],
        "email notifications": ["notifications"],
        "in-app notifications": ["notifications"],
        "realtime messaging": ["realtime"],
        "analytics": ["analytics"],
        # Pro's SubscriptionPlanController registers /subscription-plans/*; the
        # feature was reported as a gap only because it had no mapping entry.
        "vendor subscription plans": ["subscription-plans"],
        # Push devices are registered through Free's AuthController:
        # GET + POST /auth/devices. Firebase is the transport behind it.
        "push notifications": ["auth"],
        # GET /services/limits. Added in 1.7.1 - before that the ceilings were a
        # template var in the web wizard, so an app discovered them by being
        # rejected. This entry is honest only while that route exists; the
        # stale-mapping check below fails if it is ever removed.
        "service limits": ["services"],
    },
}


def read(path):
    try:
        with open(path, encoding="utf-8") as fh:
            return fh.read()
    except OSError:
        return ""


def discover_routes(root, globs):
    """Collect (rest_base, literal path) pairs from register_rest_route() calls."""
    routes = set()
    for rel in globs:
        base_dir = os.path.join(root, rel)
        if not os.path.isdir(base_dir):
            continue
        for dirpath, _dirs, files in os.walk(base_dir):
            if "dist" in dirpath or "vendor" in dirpath:
                continue
            for fname in files:
                if not fname.endswith(".php"):
                    continue
                src = read(os.path.join(dirpath, fname))
                rest_base = ""
                m = re.search(r"\$rest_base\s*=\s*'([^']+)'", src)
                if m:
                    rest_base = m.group(1)
                # register_rest_route( ns, '/literal', ... ) and the
                # '/' . $this->rest_base . '/suffix' form both appear.
                for raw in re.findall(r"register_rest_route\s*\([^,]+,\s*(.+?),", src, re.S):
                    raw = raw.strip()
                    parts = re.findall(r"'([^']*)'", raw)
                    path = "".join(parts)
                    if "rest_base" in raw and rest_base:
                        path = path.replace("/", "/" + rest_base + "/", 1) if path.startswith("/") else path
                        if rest_base not in path:
                            path = "/" + rest_base + path
                    if path:
                        routes.add(re.sub(r"/+", "/", path))
    return sorted(routes)


def parse_catalog(text):
    """Rows are `| Feature | Free | Pro |`; section headings give the group."""
    features, section = [], ""
    for line in text.splitlines():
        if line.startswith("## "):
            section = line[3:].strip()
            continue
        if not line.startswith("|") or line.startswith("|---"):
            continue
        cells = [c.strip() for c in line.strip("|").split("|")]
        if len(cells) < 3 or cells[0].lower() in ("feature", "status"):
            continue
        if section.lower().startswith("not shipping"):
            continue
        features.append({"feature": cells[0], "free": cells[1], "pro": cells[2], "section": section})
    return features


def first_segment(route):
    return route.strip("/").split("/")[0].lower()


def match_routes(feature, routes, mapping):
    """
    Match on the FIRST PATH SEGMENT, never on a substring.

    Substring matching silently reported everything as reachable: "service
    listings" matched /analytics/vendor/services and /recurring-services/ — two
    routes that have nothing to do with browsing the catalog — so the report
    claimed zero gaps without having verified a single one. A tool that answers
    "yes" when it does not know is worse than no tool, because the app team acts
    on it.
    """
    key = feature.lower()
    for needle, bases in mapping.items():
        if needle in key:
            wanted = {b.lower() for b in bases}
            hits = [r for r in routes if first_segment(r) in wanted]
            if hits:
                return sorted(hits)
    return []


def main():
    cfg = CONFIG
    free = cfg["free_dir"]
    pro = os.path.join(os.path.dirname(free), cfg["pro_dirname"])

    routes = discover_routes(free, cfg["api_globs"])
    pro_routes = discover_routes(pro, cfg["api_globs"]) if os.path.isdir(pro) else []
    all_routes = sorted(set(routes) | set(pro_routes))

    catalog_text = read(os.path.join(free, cfg["catalog"]))
    if not catalog_text:
        print(f"ERROR: catalog not found at {cfg['catalog']}", file=sys.stderr)
        return 2

    rows, gaps = [], []
    for feat in parse_catalog(catalog_text):
        key = feat["feature"].lower()
        excused = next((why for name, why in cfg["not_api_surfaces"].items() if name in key), None)
        hits = match_routes(feat["feature"], all_routes, cfg["feature_routes"])
        status = "ok" if hits else ("not-an-api-surface" if excused else "GAP")
        row = {**feat, "routes": hits, "status": status, "note": excused or ""}
        rows.append(row)
        if status == "GAP":
            gaps.append(row)

    # A declared route base that matches NO route is drift: either the endpoint was
    # renamed and the mapping rotted, or the mapping was wrong from the start. It
    # must be reported, or a silently-unmatched base becomes a false GAP that
    # someone then "fixes" by loosening the matcher until it lies again.
    known = {first_segment(r) for r in all_routes}
    stale = sorted({b for bases in cfg["feature_routes"].values() for b in bases} - known)

    if "--json" in sys.argv:
        print(json.dumps({"routes_found": len(all_routes), "features": rows, "gaps": gaps, "stale_mappings": stale}, indent=2))
    else:
        print(f"Routes discovered from source: {len(all_routes)}  "
              f"(free {len(routes)}, pro {len(pro_routes)})")
        print(f"Catalog features: {len(rows)}\n")
        section = None
        for r in rows:
            if r["section"] != section:
                section = r["section"]
                print(f"\n## {section}")
            mark = {"ok": "  OK ", "not-an-api-surface": "  -- ", "GAP": " GAP "}[r["status"]]
            detail = ", ".join(r["routes"][:3]) if r["routes"] else r["note"]
            print(f"{mark}{r['feature'][:52]:54} {detail[:70]}")
        print(f"\n{len(gaps)} feature(s) with no reachable REST route.")
        for g in gaps:
            print(f"  - {g['feature']}  (free={g['free']} pro={g['pro']})")
        if stale:
            print(f"\n{len(stale)} declared route base(s) match no route - mapping has rotted:")
            for b in stale:
                print(f"  - {b}")

    if "--check" in sys.argv and (gaps or stale):
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
