#!/usr/bin/env bash
# QA persona fixtures for WP Sell Services — idempotent.
#
# Creates every persona the role ladder needs (wp-card-qa §1.5). Safe to re-run:
# existing users are left alone, never recreated or re-roled.
#
#   bash bin/qa-fixtures.sh          # create/verify
#   bash bin/qa-fixtures.sh --list   # show state, change nothing
#
# Run once per environment at the start of a QA session, NOT once per card.
# Personas are declared in docs/qa/qa-config.json -> personas; this script is the
# executable half of that contract. Keep the two in step.
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG="${PLUGIN_DIR}/docs/qa/qa-config.json"
[[ -f "${CONFIG}" ]] || { echo "✗ missing ${CONFIG} — scaffold with /wp-plugin-release-qa"; exit 1; }

# Derive the WP root from the plugin's own location; never trust a stored path.
WP_PATH="${PLUGIN_DIR%/wp-content/plugins/*}"
[[ -f "${WP_PATH}/wp-load.php" ]] || { echo "✗ no wp-load.php above ${PLUGIN_DIR}"; exit 1; }
wp() { command wp --path="${WP_PATH}" "$@"; }

# bash 3.2-safe (macOS): one TSV blob, no mapfile / no arrays.
PERSONAS_TSV="$(python3 -c '
import json,sys
d=json.load(open(sys.argv[1]))
for p in d.get("personas",[]):
    if p.get("login"):
        print("\t".join([p["login"], p.get("role","subscriber"), p.get("email") or p["login"]+"@example.test"]))
' "${CONFIG}")"

[[ -n "${PERSONAS_TSV}" ]] || { echo "✗ no personas in ${CONFIG} — see wp-card-qa Step 0"; exit 1; }

if [[ "${1:-}" == "--list" ]]; then
  printf '%-24s %-14s %s\n' LOGIN ROLE STATUS
  while IFS=$'\t' read -r login role _; do
    if id=$(wp user get "${login}" --field=ID 2>/dev/null); then
      printf '%-24s %-14s exists (ID %s, roles: %s)\n' "${login}" "${role}" "${id}" \
        "$(wp user get "${login}" --field=roles 2>/dev/null)"
    else
      printf '%-24s %-14s MISSING\n' "${login}" "${role}"
    fi
  done <<< "${PERSONAS_TSV}"
  exit 0
fi

created=0; kept=0
while IFS=$'\t' read -r login role email; do
  if wp user get "${login}" --field=ID >/dev/null 2>&1; then
    echo "  = ${login} (${role}) already exists"
    kept=$((kept+1))
  else
    wp user create "${login}" "${email}" --role="${role}" \
       --user_pass="qa-${login}-pass" --display_name="QA ${login}" >/dev/null
    echo "  + ${login} (${role}) created"
    created=$((created+1))
  fi
done <<< "${PERSONAS_TSV}"

# --- plugin-specific fixtures -------------------------------------------------
# Vendors must actually hold vendor STATUS, not just the role: VendorService
# gates on the profile row, and set_status() refuses a transition when
# get_vendor_status() returns null. A user with the wpss_vendor role but no
# profile row looks like a vendor and behaves like nobody.
while IFS=$'\t' read -r login role _; do
  [[ "${role}" == "wpss_vendor" ]] || continue
  uid="$(wp user get "${login}" --field=ID 2>/dev/null)" || continue
  wp eval "
    \$svc = new \\WPSellServices\\Services\\VendorService();
    if ( null === \$svc->get_vendor_status( ${uid} ) ) {
        \$svc->register( ${uid} );
        echo '  + vendor profile created for ${login}' . PHP_EOL;
    } elseif ( 'active' !== \$svc->get_vendor_status( ${uid} ) ) {
        \$svc->set_status( ${uid}, 'active' );
        echo '  ~ ${login} returned to active' . PHP_EOL;
    }
  " 2>/dev/null || echo "  ! could not verify vendor status for ${login}"
done <<< "${PERSONAS_TSV}"

# The dev auto-login mu-plugin is what makes ?autologin=<login> work. Without it
# every persona switch becomes a manual form fill.
MU="${WP_PATH}/wp-content/mu-plugins/dev-auto-login.php"
if [[ -f "${MU}" ]]; then
  echo "  = dev-auto-login mu-plugin present"
else
  echo "  ! dev-auto-login mu-plugin MISSING at ${MU}"
  echo "    persona switching will not work - see CLAUDE.md > Local WordPress Testing"
fi


echo "✓ personas ready — ${created} created, ${kept} already present"
echo "  log in with ?autologin=<login> on any front-end URL"
