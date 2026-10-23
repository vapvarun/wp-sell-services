#!/usr/bin/env bash
# Runs every `checks.static` guard in docs/qa/qa-config.json and prints "guards done, red=N".
# The wp-plugin-qa MCP does not replay `static` guards, so this is the one repo-owned gate for them.
# Usage: bin/run-guards.sh [id ...]   (WP_CLI=... to use a wrapper; run from anywhere)
cd "$(dirname "$0")/.." || exit 1
noise='opcache|Xdebug|Zend|imagick|Derick|^$|PHP Startup|Colors.php'
want=" $* "
red=0
while IFS=$'\t' read -r id cmd; do
	[ "$want" != "  " ] && [[ "$want" != *" $id "* ]] && continue
	out=$(cd "$(git rev-parse --show-toplevel)/../../.." && WP_CLI="${WP_CLI:-wp}" bash -c "cd wp-content/plugins/$(basename "$OLDPWD") && $cmd" 2>&1 | grep -vE "$noise")
	if echo "$out" | grep -qE '^FAIL|Fatal error|Uncaught'; then
		echo "RED $id"; echo "$out" | grep -E '^FAIL|Fatal|Uncaught' | head -3; red=$((red + 1))
	fi
done < <(python3 -c "import json;[print(c['id']+'\t'+c['run']) for c in json.load(open('docs/qa/qa-config.json'))['checks']['static']]")
echo "guards done, red=$red"
[ "$red" -eq 0 ]
