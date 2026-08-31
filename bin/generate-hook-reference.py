#!/usr/bin/env python3
"""Generate the complete hook reference from source.

The curated guide at developer-guide/hooks-filters.md stays hand-written: it is
grouped, it explains when to reach for a hook, and that is worth a person's
time. What it cannot be is COMPLETE - 508 hooks are fired across the two
plugins and 237 of them appear in no document at all (Basecamp 10239807296).

So this writes the other half: every hook, with the file and line it fires
from, its type, its argument count and the first line of its docblock. It is
regenerated, never edited. docs-audit.py fails when it is stale, which is what
stops it rotting the way the hand-maintained citations did.

Usage:
    python3 bin/generate-hook-reference.py           # write the file
    python3 bin/generate-hook-reference.py --check   # exit 1 if stale
"""

import os
import re
import sys

FREE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PRO = os.path.join(os.path.dirname(FREE), "wp-sell-services-pro")
OUT = os.path.join(FREE, "docs/website/developer-guide/hooks-reference.md")

# do_action / apply_filters with a literal wpss_ name. Deliberately literal
# only: a hook built from a variable cannot be documented by name, and guessing
# is how phantom entries get into a reference.
CALL = re.compile(
    r"\b(do_action|apply_filters)(?:_ref_array|_deprecated)?\s*\(\s*['\"](wpss_[a-z0-9_]+)['\"]([^;]*)",
    re.S,
)

# The last /** ... */ before the call, so a documented hook keeps its summary.
DOCBLOCK = re.compile(r"/\*\*(.*?)\*/", re.S)


def summarise(block):
    """First real sentence of a docblock, flattened to one line."""
    lines = []
    for raw in block.splitlines():
        line = raw.strip().lstrip("*").strip()
        if not line or line.startswith("@"):
            if lines:
                break
            continue
        lines.append(line)
        if line.endswith("."):
            break
    text = " ".join(lines).strip()

    # WordPress's own "documented elsewhere" convention. It is correct in the
    # source and useless in a reference table - it points at a file rather than
    # saying what the hook does, so leave the cell empty instead.
    if re.match(r"^This (action|filter|hook) is documented in ", text, re.I):
        return ""

    return text.replace("|", "\\|")[:160]


def arg_count(tail):
    """Arguments passed after the hook name, counted at depth zero."""
    depth = 0
    args = 0
    seen = False
    for ch in tail:
        if ch in "([":
            depth += 1
        elif ch in ")]":
            if depth == 0:
                break
            depth -= 1
        elif ch == "," and depth == 0:
            args += 1
        elif not ch.isspace() and depth == 0:
            seen = True
    return args if seen else 0


def scan():
    found = {}
    roots = [
        ("Free", os.path.join(FREE, "src")),
        ("Free", os.path.join(FREE, "templates")),
        ("Pro", os.path.join(PRO, "src")),
        ("Pro", os.path.join(PRO, "templates")),
    ]

    for plugin, root in roots:
        if not os.path.isdir(root):
            continue
        base = FREE if plugin == "Free" else PRO
        for dirpath, _, filenames in os.walk(root):
            for name in sorted(filenames):
                if not name.endswith(".php"):
                    continue
                path = os.path.join(dirpath, name)
                rel = os.path.relpath(path, base)
                try:
                    src = open(path, encoding="utf-8", errors="ignore").read()
                except OSError:
                    continue

                for m in CALL.finditer(src):
                    kind, hook, tail = m.group(1), m.group(2), m.group(3)
                    line = src.count("\n", 0, m.start()) + 1

                    blocks = list(DOCBLOCK.finditer(src, 0, m.start()))
                    summary = ""
                    if blocks:
                        last = blocks[-1]
                        between = src[last.end():m.start()]
                        # Only claim the docblock if nothing substantial sits
                        # between it and the call.
                        if len(between.strip()) < 400:
                            summary = summarise(last.group(1))

                    found.setdefault(hook, []).append(
                        {
                            "plugin": plugin,
                            "file": rel,
                            "line": line,
                            "type": "action" if kind == "do_action" else "filter",
                            "args": arg_count(tail),
                            "summary": summary,
                        }
                    )
    return found


def render(found):
    total_sites = sum(len(v) for v in found.values())
    out = [
        "# Hook reference (generated)",
        "",
        "<!-- GENERATED FILE - DO NOT EDIT.",
        "     Written by bin/generate-hook-reference.py from the source of both",
        "     plugins. Edits here are lost on the next run; docs-audit.py fails",
        "     when this file is out of date. For the curated, grouped guide with",
        "     examples, see hooks-filters.md. -->",
        "",
        "Every hook fired by WP Sell Services and WP Sell Services Pro, taken from",
        "source rather than maintained by hand. `hooks-filters.md` is the readable",
        "guide; this is the complete index.",
        "",
        f"**{len(found)} hooks** across **{total_sites}** firing sites.",
        "",
    ]

    for kind, label in (("action", "Actions"), ("filter", "Filters")):
        names = sorted(h for h, sites in found.items() if any(s["type"] == kind for s in sites))
        out += [f"## {label} ({len(names)})", "", "| Hook | Args | Fired from | Description |", "|---|---|---|---|"]
        for hook in names:
            sites = [s for s in found[hook] if s["type"] == kind]
            first = sites[0]
            where = f"`{first['file']}:{first['line']}`"
            if len(sites) > 1:
                where += f" *(+{len(sites) - 1} more)*"
            if first["plugin"] == "Pro":
                where += " **[PRO]**"
            out.append(f"| `{hook}` | {first['args']} | {where} | {first['summary']} |")
        out.append("")

    return "\n".join(out).rstrip() + "\n"


def main():
    body = render(scan())

    if "--check" in sys.argv:
        current = open(OUT, encoding="utf-8").read() if os.path.exists(OUT) else ""
        if current != body:
            print("hooks-reference.md is out of date. Run: python3 bin/generate-hook-reference.py")
            return 1
        print("hooks-reference.md is current.")
        return 0

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, "w", encoding="utf-8") as fh:
        fh.write(body)
    print(f"Wrote {os.path.relpath(OUT, FREE)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
