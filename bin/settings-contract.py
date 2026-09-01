#!/usr/bin/env python3
"""Every setting must be written by something and read by something.

The defect class this exists for, all found by hand in one week:

  auto_payout_to_wallet   read by WalletManager, written by nothing -> the whole
                          Pro wallet payout path was dead on every install
  require_verification    seeded on activation, read by nothing, ever
  notify_moderation       read by EmailService, no control wrote it, so
                          moderation email could not be switched off
  push tokens             stored from 1.1.0, read by nothing until 1.6.0
  checkout_account_creation  written and read by nothing that acts on it

A key with a reader and no writer is a feature nobody can turn on. A key with a
writer and no reader is a setting that does nothing. Both look fine on screen,
which is why they survive review and reach customers.

    python3 bin/settings-contract.py           # report, exit 1 on findings
    python3 bin/settings-contract.py --quiet   # findings only

Pro is picked up automatically as a sibling directory when present.
"""
import os
import re
import sys

QUIET = "--quiet" in sys.argv
LEADS = "--leads" in sys.argv  # also print the lower-confidence list
FREE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PRO = os.path.join(os.path.dirname(FREE), "wp-sell-services-pro")

SKIP_DIRS = ("vendor", "node_modules", "/dist/", "/tests/", "/libs/")

# Keys that are structural rather than settings: read straight off a row or a
# request, never stored in a wpss_* option array.
IGNORE = {"id", "key", "type", "label", "value", "name", "title", "default"}


def php_files(*roots):
    for root in roots:
        if not os.path.isdir(root):
            continue
        for r, _, fs in os.walk(root):
            if any(s in r for s in SKIP_DIRS):
                continue
            for n in fs:
                if n.endswith(".php"):
                    yield os.path.join(r, n)


# A write is a settings key being persisted or declared as a field.
WRITE_RES = [
    re.compile(r"\$sanitized\[\s*'([a-z0-9_]+)'\s*\]\s*="),
    re.compile(r"'field'\s*=>\s*'([a-z0-9_]+)'"),
    re.compile(r"add_settings_field\(\s*'([a-z0-9_]+)'"),
]

# Files whose sanitize_* handles a FORM SUBMISSION rather than a settings page.
# Their $sanitized[...] keys are request fields, not stored settings, so they
# are not part of this contract.
NOT_SETTINGS_FILES = (
    "PublicSignup.php", "ServiceWizard.php", "GalleryService.php",
    "ServiceCommands.php", "BuyerRequestArchiveView.php",
)


def scan():
    """Return (reads, writes) as key -> set of files.

    A read is deliberately ANY other mention of the quoted key. The earlier
    version matched only wpss_get_option() and $settings['key'], and reported 73
    findings of which nearly all were real reads in a shape it did not know -
    max_services_per_vendor alone is read in ten places. A checker that cries
    wolf gets switched off, so this one under-reports rather than over-reports:
    if the string appears anywhere that is not a write, the key has a reader.
    """
    reads, writes = {}, {}
    bodies = {}

    for path in php_files(os.path.join(FREE, "src"), os.path.join(FREE, "templates"),
                          os.path.join(PRO, "src"), os.path.join(PRO, "templates")):
        try:
            bodies[path] = open(path, errors="ignore").read()
        except OSError:
            continue

    for path, body in bodies.items():
        if any(skip in path for skip in NOT_SETTINGS_FILES):
            continue
        rel = os.path.relpath(path, os.path.dirname(FREE))
        for pattern in WRITE_RES:
            for key in pattern.findall(body):
                writes.setdefault(key, set()).add(rel)

    # Now look for any non-write mention of each written key, plus keys that are
    # read through wpss_get_option() but never written anywhere.
    read_re = re.compile(r"wpss_get_option\(\s*'([a-z0-9_]+)'\s*,\s*'([a-z0-9_]+)'")

    # A key read straight off a fetched wpss_* option array. This is how
    # auto_payout_to_wallet hid: WalletManager did get_option('wpss_vendor')
    # then $settings['auto_payout_to_wallet'], so a checker looking only for
    # wpss_get_option() saw neither a read nor a write and said nothing, while
    # the flag gated the entire Pro wallet payout path on every install.
    array_read_re = re.compile(r"\$(?:settings|options|opts|general|vendor_settings|order_settings)\[\s*'([a-z0-9_]+)'\s*\]")

    for path, body in bodies.items():
        rel = os.path.relpath(path, os.path.dirname(FREE))
        is_form = any(skip in path for skip in NOT_SETTINGS_FILES)

        for _, key in read_re.findall(body):
            reads.setdefault(key, set()).add(rel)

        if not is_form:
            for key in array_read_re.findall(body):
                if key not in IGNORE:
                    reads.setdefault(key, set()).add(rel)

        for key in writes:
            if f"'{key}'" not in body and f'"{key}"' not in body:
                continue
            # Strip the write forms, then see if the key still appears.
            stripped = body
            for pattern in WRITE_RES:
                stripped = pattern.sub("", stripped)
            if f"'{key}'" in stripped or f'"{key}"' in stripped:
                reads.setdefault(key, set()).add(rel)

    return reads, writes


def main():
    reads, writes = scan()

    # Two confidence levels, because the write shapes differ between the two
    # plugins and a checker that mixes them is a checker nobody trusts.
    #
    # CONFIRMED: read through wpss_get_option( group, key ) - the one
    # unambiguous read - and written nowhere. No false positive has survived
    # this rule.
    #
    # LIKELY: read as $settings['key'] off a fetched option. This is how
    # auto_payout_to_wallet hid an entire dead payout path, so it is worth
    # reporting - but Pro's settings screens persist through renderers this
    # does not recognise as writes, so treat each as a lead to check rather
    # than a defect. Narrow the gap by teaching WRITE_RES Pro's shapes.
    read_re = re.compile(r"wpss_get_option\(\s*'([a-z0-9_]+)'\s*,\s*'([a-z0-9_]+)'")
    confirmed_reads = set()

    for path in php_files(os.path.join(FREE, "src"), os.path.join(FREE, "templates"),
                          os.path.join(PRO, "src"), os.path.join(PRO, "templates")):
        try:
            body = open(path, errors="ignore").read()
        except OSError:
            continue
        for _, key in read_re.findall(body):
            confirmed_reads.add(key)

    no_writer = sorted(k for k in reads if k not in writes and k not in IGNORE)
    confirmed = [k for k in no_writer if k in confirmed_reads]
    likely = [k for k in no_writer if k not in confirmed_reads]
    no_reader = sorted(k for k in writes if k not in reads and k not in IGNORE)

    if confirmed or no_reader:
        print("CONFIRMED")
        for key in confirmed:
            print(f"  NO WRITER   {key:<32} read at {sorted(reads[key])[0]}")
        for key in no_reader:
            print(f"  NO READER   {key:<32} written at {sorted(writes[key])[0]}")

    # Behind a flag. Printing 29 unverified leads on every run is how a check
    # earns a reputation for noise and stops being read - and the three
    # CONFIRMED lines are the ones worth acting on today.
    if likely and LEADS:
        print("\nLIKELY - read as $settings['key'], verify the write shape")
        for key in likely:
            print(f"  NO WRITER   {key:<32} read at {sorted(reads[key])[0]}")
    elif likely and not QUIET:
        print(f"\n  {len(likely)} lower-confidence lead(s); --leads to list them.")

    hard = len(confirmed) + len(no_reader)

    if not hard and not likely:
        if not QUIET:
            print(f"  every setting has a writer and a reader "
                  f"({len(reads)} read, {len(writes)} written)")
        return 0

    print(f"\n{hard} confirmed, {len(likely)} to verify.")
    return 1 if hard else 0


if __name__ == "__main__":
    sys.exit(main())
