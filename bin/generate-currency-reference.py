#!/usr/bin/env python3
"""Generate the supported-currency table from wpss_get_currency_registry().

Two pages carried hand-typed ten-row tables that disagreed with each other and
with the registry (Euro as "E", Rupee as "R", 148 currencies supported). The
registry is the only source; this writes the one table both pages link to.
docs-audit.py runs --check, so the page cannot go stale.

Usage:
    python3 bin/generate-currency-reference.py           # write the file
    python3 bin/generate-currency-reference.py --check   # exit 1 if stale
"""

import os
import re
import sys

FREE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(FREE, "src/functions/money.php")
OUT = os.path.join(FREE, "docs/website/payments-checkout/currency-reference.md")


def registry():
    body = open(SRC, encoding="utf-8").read()
    start = body.index("function wpss_get_currency_registry()")
    end = body.index("apply_filters", start)
    entry = re.compile(
        r"'([A-Z]{3})'\s*=>\s*array\(\s*'name'\s*=>\s*__\(\s*'((?:[^'\\]|\\.)*)'.*?"
        r"'symbol'\s*=>\s*'((?:[^'\\]|\\.)*)',\s*'decimals'\s*=>\s*(\d)",
        re.S,
    )
    rows = [(c, n.replace("\\'", "'"), s.replace("\\'", "'"), int(d)) for c, n, s, d in entry.findall(body[start:end])]
    if not rows:
        sys.exit("generate-currency-reference: no currencies parsed from money.php")
    return rows


def render(rows):
    out = [
        "# Supported currencies",
        "",
        "<!-- GENERATED FILE - DO NOT EDIT.",
        "     Written by bin/generate-currency-reference.py from",
        "     wpss_get_currency_registry() in src/functions/money.php.",
        "     docs-audit.py fails when this file is out of date. -->",
        "",
        "These are the %d currencies the marketplace can use as its store currency. "
        "Pick one under **Settings > General > Currency**; see [Currency and tax](currency-tax-config.md). "
        "A site can add or remove entries with the `wpss_currencies` filter." % len(rows),
        "",
        "| Currency | Code | Symbol | Decimals |",
        "|----------|------|--------|----------|",
    ]
    for code, name, symbol, dec in sorted(rows, key=lambda r: r[1].lower()):
        out.append("| %s | %s | %s | %d |" % (name, code, symbol.replace("|", "\\|"), dec))
    return "\n".join(out) + "\n"


if __name__ == "__main__":
    text = render(registry())
    if "--check" in sys.argv:
        current = open(OUT, encoding="utf-8").read() if os.path.exists(OUT) else ""
        if current != text:
            print("currency-reference.md is out of date: run python3 bin/generate-currency-reference.py")
            sys.exit(1)
        sys.exit(0)
    open(OUT, "w", encoding="utf-8").write(text)
    print("wrote %s" % os.path.relpath(OUT, FREE))
