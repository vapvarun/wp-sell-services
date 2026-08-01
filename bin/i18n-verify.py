#!/usr/bin/env python3
"""i18n verification gate for WP Sell Services (free + pro).

Plugin-agnostic on purpose: everything plugin-specific is read from
``.i18n-config.json`` in the plugin root, so this file is byte-identical in
``wp-sell-services/bin/`` and ``wp-sell-services-pro/bin/``. The free copy is
canonical -- change it there, then copy it across (same convention the bundled
EDD SDK uses).

Three checks, each a release blocker:

  1. STALE POT     -- regenerate the POT into a temp dir with the exact args the
                      build uses and diff it against the committed POT, ignoring
                      the volatile ``POT-Creation-Date`` header. A stale POT means
                      translators never see the strings added since the last
                      release.
  2. TEXT DOMAIN   -- every gettext call in plugin-owned PHP must pass this
                      plugin's own text domain, spelled as a literal. A wrong or
                      missing domain silently sends the string to a catalog that
                      will never contain it.
  3. SCRIPT I18N   -- any JS file that calls ``wp.i18n.*`` must be registered
                      under a handle that also gets ``wp_set_script_translations()``
                      (directly, or via a recognised ScriptRegistry helper) and
                      must declare ``wp-i18n`` as a script dependency. Without
                      both, the JSON catalog is never loaded and the strings stay
                      English no matter what the translator ships.

Usage:
    bin/i18n-verify.py [--root DIR] [--skip-pot] [--list-handles] [-v]

Exit codes: 0 clean, 1 one or more violations, 2 harness/config problem.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path

# Gettext function -> zero-based index of the text-domain argument.
GETTEXT_DOMAIN_ARG = {
    '__': 1,
    '_e': 1,
    'esc_html__': 1,
    'esc_html_e': 1,
    'esc_attr__': 1,
    'esc_attr_e': 1,
    '_x': 2,
    '_ex': 2,
    'esc_html_x': 2,
    'esc_attr_x': 2,
    '_n': 3,
    '_nx': 4,
    '_n_noop': 2,
    '_nx_noop': 3,
}

# Anything that looks like a translated string in JS.
JS_I18N_RE = re.compile(
    r'\bwp\.i18n\.(?:__|_x|_n|_nx|sprintf)\s*\(|'
    r'\bwp\.i18n\b|'
    r"\bfrom\s+['\"]@wordpress/i18n['\"]"
)

STRING_LITERAL_RE = re.compile(r"""^\s*(['"])(.*)\1\s*$""", re.DOTALL)


class Violation:
    def __init__(self, check: str, path: str, line: int, message: str, hint: str = ''):
        self.check = check
        self.path = path
        self.line = line
        self.message = message
        self.hint = hint

    def render(self) -> str:
        where = f'{self.path}:{self.line}' if self.line else self.path
        out = f'  [{self.check}] {where}\n      {self.message}'
        if self.hint:
            out += f'\n      -> {self.hint}'
        return out


# ---------------------------------------------------------------------------
# Tiny PHP call-site reader
# ---------------------------------------------------------------------------

def split_call_args(src: str, open_paren: int):
    """Return (args, end_index) for the call whose '(' sits at ``open_paren``.

    Walks the source character by character honouring PHP single/double quoted
    strings (with backslash escapes), heredocs are not handled -- none of the
    call sites we scan use them -- and nested brackets. Returns ``(None, -1)``
    when the call never closes (truncated / unparseable).
    """
    args = []
    depth = 0
    buf = []
    i = open_paren
    n = len(src)
    quote = None
    while i < n:
        ch = src[i]
        if quote:
            buf.append(ch)
            if ch == '\\':
                if i + 1 < n:
                    buf.append(src[i + 1])
                    i += 2
                    continue
            elif ch == quote:
                quote = None
            i += 1
            continue
        if ch in "'\"":
            quote = ch
            buf.append(ch)
            i += 1
            continue
        if ch == '/' and i + 1 < n and src[i + 1] == '/':
            j = src.find('\n', i)
            i = n if j == -1 else j
            continue
        if ch == '#':
            j = src.find('\n', i)
            i = n if j == -1 else j
            continue
        if ch == '/' and i + 1 < n and src[i + 1] == '*':
            j = src.find('*/', i)
            i = n if j == -1 else j + 2
            continue
        if ch in '([{':
            depth += 1
            if depth == 1 and ch == '(':
                i += 1
                continue
            buf.append(ch)
            i += 1
            continue
        if ch in ')]}':
            depth -= 1
            if depth == 0:
                args.append(''.join(buf).strip())
                if len(args) == 1 and args[0] == '':
                    args = []
                return args, i
            buf.append(ch)
            i += 1
            continue
        if ch == ',' and depth == 1:
            args.append(''.join(buf).strip())
            buf = []
            i += 1
            continue
        buf.append(ch)
        i += 1
    return None, -1


def literal_value(arg: str):
    """Return the PHP string literal's value, or None when not a plain literal."""
    if arg is None:
        return None
    m = STRING_LITERAL_RE.match(arg)
    if not m:
        return None
    quote, body = m.group(1), m.group(2)
    if quote == "'":
        return body.replace("\\'", "'").replace('\\\\', '\\')
    if '$' in body or '{' in body:
        return None
    return body.replace('\\"', '"').replace('\\\\', '\\')


def line_of(src: str, index: int) -> int:
    return src.count('\n', 0, index) + 1


def mask_comments(src: str) -> str:
    """Blank out PHP comments while preserving offsets and line numbers.

    Docblocks routinely *mention* gettext calls ("avoid calling __() before
    init") and every one of those would otherwise be reported as a call site
    with a missing text domain. Replacing comment bytes with spaces keeps every
    later index and line number identical to the original file.
    """
    out = list(src)
    i = 0
    n = len(src)
    quote = None
    while i < n:
        ch = src[i]
        if quote:
            if ch == '\\':
                i += 2
                continue
            if ch == quote:
                quote = None
            i += 1
            continue
        if ch in "'\"":
            quote = ch
            i += 1
            continue
        if ch == '/' and i + 1 < n and src[i + 1] == '/':
            j = src.find('\n', i)
            j = n if j == -1 else j
            for k in range(i, j):
                out[k] = ' '
            i = j
            continue
        if ch == '#':
            j = src.find('\n', i)
            j = n if j == -1 else j
            for k in range(i, j):
                out[k] = ' '
            i = j
            continue
        if ch == '/' and i + 1 < n and src[i + 1] == '*':
            j = src.find('*/', i + 2)
            j = n if j == -1 else j + 2
            for k in range(i, j):
                if out[k] != '\n':
                    out[k] = ' '
            i = j
            continue
        i += 1
    return ''.join(out)


def iter_calls(src: str, names):
    """Yield (name, args, start_index) for every top-level call to ``names``."""
    pattern = re.compile(
        r'(?<![\w$>])(?:\\)?(' + '|'.join(re.escape(n) for n in names) + r')\s*\(')
    for m in pattern.finditer(src):
        # Skip `function __(` declarations and `->__(` method calls.
        prefix = src[max(0, m.start() - 40):m.start()]
        if re.search(r'\bfunction\s+$', prefix):
            continue
        args, _end = split_call_args(src, m.end() - 1)
        if args is None:
            continue
        yield m.group(1), args, m.start()


# ---------------------------------------------------------------------------
# File walking
# ---------------------------------------------------------------------------

def walk_files(root: Path, suffix: str, exclude_dirs):
    excluded = {e.strip('/') for e in exclude_dirs}
    for dirpath, dirnames, filenames in os.walk(root):
        rel_dir = Path(dirpath).relative_to(root)
        parts = set(rel_dir.parts)
        dirnames[:] = [
            d for d in dirnames
            if not d.startswith('.') and str((rel_dir / d)) not in excluded and d not in excluded
        ]
        if parts & excluded:
            continue
        for name in sorted(filenames):
            if name.endswith(suffix):
                yield Path(dirpath) / name


# ---------------------------------------------------------------------------
# Check 1 -- stale POT
# ---------------------------------------------------------------------------

def strip_volatile(pot: str) -> str:
    out = []
    for line in pot.splitlines():
        if line.startswith('"POT-Creation-Date:'):
            continue
        out.append(line)
    return '\n'.join(out)


def check_pot(root: Path, cfg: dict, verbose: bool):
    violations = []
    pot_path = root / cfg['potFile']
    if not pot_path.exists():
        return [Violation('stale-pot', cfg['potFile'], 0,
                          'POT file is missing entirely.',
                          'Run `npm run i18n` and commit the generated catalog.')]

    with tempfile.TemporaryDirectory() as tmp:
        fresh = Path(tmp) / 'fresh.pot'
        args = [
            'wp', 'i18n', 'make-pot', '.', str(fresh),
            f"--slug={cfg['slug']}",
            f"--domain={cfg['domain']}",
            '--exclude=' + ','.join(cfg['potExclude']),
            '--headers=' + json.dumps(cfg['potHeaders'], separators=(',', ':')),
        ]
        if verbose:
            print('  $ ' + ' '.join(args))
        try:
            proc = subprocess.run(args, cwd=str(root), capture_output=True, text=True)
        except FileNotFoundError:
            print('i18n-verify: WP-CLI (`wp`) not found -- cannot verify POT freshness.',
                  file=sys.stderr)
            print('             Install WP-CLI or re-run with --skip-pot.', file=sys.stderr)
            sys.exit(2)
        if proc.returncode != 0:
            print(proc.stdout, file=sys.stderr)
            print(proc.stderr, file=sys.stderr)
            print('i18n-verify: `wp i18n make-pot` failed -- cannot verify POT freshness.',
                  file=sys.stderr)
            sys.exit(2)
        if not fresh.exists():
            print('i18n-verify: `wp i18n make-pot` produced no output file.', file=sys.stderr)
            sys.exit(2)

        committed = strip_volatile(pot_path.read_text(encoding='utf-8'))
        regenerated = strip_volatile(fresh.read_text(encoding='utf-8'))

    if committed == regenerated:
        return violations

    # Report the msgids that differ, not a raw diff -- the offending source
    # reference is what a dev needs.
    def msgid_refs(pot: str):
        refs = {}
        current_refs = []
        for line in pot.splitlines():
            if line.startswith('#:'):
                current_refs.append(line[2:].strip())
            elif line.startswith('msgid "') and line != 'msgid ""':
                refs[line] = list(current_refs)
                current_refs = []
            elif line.startswith('msgid ""'):
                current_refs = []
        return refs

    old_refs = msgid_refs(committed)
    new_refs = msgid_refs(regenerated)
    added = [k for k in new_refs if k not in old_refs]
    removed = [k for k in old_refs if k not in new_refs]

    detail = []
    for k in added[:10]:
        ref = new_refs[k][0] if new_refs[k] else '(no reference)'
        detail.append(f'+ {ref}  {k[:90]}')
    for k in removed[:10]:
        ref = old_refs[k][0] if old_refs[k] else '(no reference)'
        detail.append(f'- {ref}  {k[:90]}')
    extra = len(added) + len(removed) - len(detail)
    if extra > 0:
        detail.append(f'... and {extra} more')
    if not detail:
        detail.append('(headers or line references changed)')

    violations.append(Violation(
        'stale-pot', cfg['potFile'], 0,
        f'POT is stale: {len(added)} string(s) added, {len(removed)} removed since it was generated.\n      '
        + '\n      '.join(detail),
        'Run `npm run i18n` and commit languages/*.pot, *.mo and *.json.'))
    return violations


# ---------------------------------------------------------------------------
# Check 2 -- text domain
# ---------------------------------------------------------------------------

def check_text_domain(root: Path, cfg: dict):
    violations = []
    expected = cfg['domain']
    allowed = set(cfg.get('allowedDomains') or []) | {expected}
    names = list(GETTEXT_DOMAIN_ARG)

    for path in walk_files(root, '.php', cfg['domainScanExclude']):
        rel = str(path.relative_to(root))
        try:
            raw = path.read_text(encoding='utf-8', errors='replace')
        except OSError:
            continue
        if not any(f'{n}(' in raw or f'{n} (' in raw for n in names):
            continue
        src = mask_comments(raw)
        for name, args, start in iter_calls(src, names):
            idx = GETTEXT_DOMAIN_ARG[name]
            line = line_of(src, start)
            if len(args) <= idx:
                violations.append(Violation(
                    'text-domain', rel, line,
                    f"{name}() is missing its text-domain argument.",
                    f"Add '{expected}' as argument {idx + 1}; without it the string "
                    f"goes to WordPress core's 'default' catalog and is never translated."))
                continue
            value = literal_value(args[idx])
            if value is None:
                violations.append(Violation(
                    'text-domain', rel, line,
                    f"{name}() text domain is not a plain string literal: {args[idx][:60]}",
                    'Translation extractors only read literals -- inline the domain.'))
                continue
            if value not in allowed:
                violations.append(Violation(
                    'text-domain', rel, line,
                    f"{name}() uses text domain '{value}', expected '{expected}'.",
                    f"This plugin only ships languages/{expected}-*.mo, so '{value}' "
                    f"resolves to nothing here."))
    return violations


# ---------------------------------------------------------------------------
# Check 3 -- script translations wiring
# ---------------------------------------------------------------------------

def resolve_class_consts(src: str):
    consts = {}
    for m in re.finditer(r"\bconst\s+([A-Z_][A-Z0-9_]*)\s*=\s*(['\"])(.*?)\2", src):
        consts[m.group(1)] = m.group(3)
    return consts


def resolve_handle(arg: str, consts: dict):
    value = literal_value(arg)
    if value is not None:
        return value
    m = re.match(r'^(?:self|static)::([A-Z_][A-Z0-9_]*)$', arg.strip())
    if m:
        return consts.get(m.group(1))
    return None


def deps_declare(deps_expr: str, src: str, needle: str) -> bool:
    """True when ``needle`` is (provably) in a wp_*_script() dependency array.

    The array is usually an inline literal, but it is sometimes built into a
    local first (BlocksManager merges block.asset.php dependencies). When the
    expression references variables, every assignment to those variables in the
    same file is inspected too -- so an indirectly-built array still counts.
    """
    if needle in deps_expr:
        return True
    for var in set(re.findall(r'\$([A-Za-z_]\w*)', deps_expr)):
        for m in re.finditer(r'\$' + re.escape(var) + r'\s*(?:=|\[\s*\]\s*=)', src):
            tail = src[m.end():m.end() + 600]
            stop = tail.find(';')
            if needle in (tail if stop == -1 else tail[:stop]):
                return True
    return False


def js_path_from_src_expr(expr: str):
    """Pull the plugin-relative .js path out of a wp_*_script() src expression."""
    for m in re.finditer(r"""(['"])([^'"]*?\.js)\1""", expr):
        candidate = m.group(2)
        if candidate.startswith(('http://', 'https://', '//')):
            continue
        return candidate.lstrip('/')
    return None


def check_script_translations(root: Path, cfg: dict, list_handles: bool):
    violations = []
    helpers = cfg.get('scriptRegisterHelpers') or []
    register_names = ['wp_register_script', 'wp_enqueue_script'] + helpers

    # handle -> list of (rel_php, line, js_rel_path, deps_expr, via_helper)
    registrations = {}
    translated = set()
    php_sources = {}

    for path in walk_files(root, '.php', cfg['scriptScanExclude']):
        rel = str(path.relative_to(root))
        raw = path.read_text(encoding='utf-8', errors='replace')
        if 'script' not in raw:
            continue
        src = mask_comments(raw)
        consts = resolve_class_consts(src)

        for name, args, start in iter_calls(src, ['wp_set_script_translations']):
            if not args:
                continue
            handle = resolve_handle(args[0], consts)
            if handle:
                translated.add(handle)

        for name, args, start in iter_calls(src, register_names):
            if not args:
                continue
            handle = resolve_handle(args[0], consts)
            if not handle:
                continue
            via_helper = name in helpers
            if via_helper:
                translated.add(handle)
            js_rel = js_path_from_src_expr(args[1]) if len(args) > 1 else None
            deps = args[2] if len(args) > 2 else ''
            php_sources[rel] = src
            registrations.setdefault(handle, []).append(
                (rel, line_of(src, start), js_rel, deps, via_helper))

    if list_handles:
        print(f'{"handle":38} {"i18n":5} src')
        for handle in sorted(registrations):
            for rel, line, js_rel, _deps, via in registrations[handle]:
                flag = 'yes' if handle in translated else 'NO'
                print(f'{handle:38} {flag:5} {js_rel or "-"}   ({rel}:{line})')
        print()

    # Which JS files actually speak wp.i18n?
    i18n_js = {}
    excluded_js = {e.strip('/') for e in (cfg.get('jsScanExclude') or [])}
    for scan_dir in cfg.get('jsScanDirs') or []:
        base = root / scan_dir
        if not base.is_dir():
            continue
        for path in sorted(base.rglob('*.js')):
            rel = str(path.relative_to(root))
            if rel.endswith('.min.js'):
                continue
            if any(rel == e or rel.startswith(e + '/') for e in excluded_js):
                continue
            text = mask_comments(path.read_text(encoding='utf-8', errors='replace'))
            m = JS_I18N_RE.search(text)
            if m:
                i18n_js[rel] = line_of(text, m.start())

    for js_rel, js_line in sorted(i18n_js.items()):
        matching = [
            (handle, rel, line, deps)
            for handle, entries in registrations.items()
            for (rel, line, cand, deps, _via) in entries
            if cand and (cand == js_rel or js_rel.endswith('/' + cand) or cand.endswith('/' + js_rel))
        ]
        if not matching:
            violations.append(Violation(
                'script-i18n', js_rel, js_line,
                'calls wp.i18n.* but no wp_register_script()/wp_enqueue_script() '
                'anywhere registers this file.',
                'An unregistered script can never receive a translation catalog.'))
            continue
        for handle, rel, line, deps in matching:
            if handle not in translated:
                violations.append(Violation(
                    'script-i18n', rel, line,
                    f"handle '{handle}' registers {js_rel}, which calls wp.i18n.*, "
                    f"but nothing calls wp_set_script_translations( '{handle}', ... ).",
                    f"Register it through ScriptRegistry (which pairs the two) or add "
                    f"wp_set_script_translations( '{handle}', '{cfg['domain']}', "
                    f"<plugin dir> . '{cfg['languagesDir']}' ) beside the registration."))
            if deps and not deps_declare(deps, php_sources.get(rel, ''), 'wp-i18n'):
                violations.append(Violation(
                    'script-i18n', rel, line,
                    f"handle '{handle}' registers {js_rel}, which calls wp.i18n.*, "
                    f"but 'wp-i18n' is not in its dependency array.",
                    "Without the dependency, wp.i18n may be undefined at run time and "
                    "the catalog is printed after the script executes."))
    return violations


# ---------------------------------------------------------------------------

def main() -> int:
    parser = argparse.ArgumentParser(description='i18n verification gate.')
    parser.add_argument('--root', default=None,
                        help='Plugin root (defaults to the parent of bin/).')
    parser.add_argument('--skip-pot', action='store_true',
                        help='Skip the POT freshness check (no WP-CLI available).')
    parser.add_argument('--list-handles', action='store_true',
                        help='Print every script handle and whether it is translated.')
    parser.add_argument('-v', '--verbose', action='store_true')
    opts = parser.parse_args()

    root = Path(opts.root).resolve() if opts.root else Path(__file__).resolve().parent.parent
    cfg_path = root / '.i18n-config.json'
    if not cfg_path.exists():
        print(f'i18n-verify: no .i18n-config.json in {root}', file=sys.stderr)
        return 2
    cfg = json.loads(cfg_path.read_text(encoding='utf-8'))

    if opts.verbose:
        print(f'i18n-verify: {cfg["domain"]} @ {root}')

    violations = []
    violations += check_text_domain(root, cfg)
    violations += check_script_translations(root, cfg, opts.list_handles)
    if opts.skip_pot:
        if opts.verbose:
            print('  (POT freshness check skipped)')
    else:
        violations += check_pot(root, cfg, opts.verbose)

    if not violations:
        if opts.verbose or opts.list_handles:
            print(f'i18n-verify: clean ({cfg["domain"]}).')
        return 0

    by_check = {}
    for v in violations:
        by_check.setdefault(v.check, []).append(v)

    print(f'i18n-verify: {len(violations)} violation(s) in {cfg["domain"]}\n', file=sys.stderr)
    for check in ('stale-pot', 'text-domain', 'script-i18n'):
        items = by_check.get(check)
        if not items:
            continue
        print(f'{check} ({len(items)}):', file=sys.stderr)
        for v in items:
            print(v.render(), file=sys.stderr)
        print('', file=sys.stderr)
    return 1


if __name__ == '__main__':
    sys.exit(main())
