#!/usr/bin/env bash
# Record what every check actually executes, into audit/render-coverage.json.
#
# Usage (from anywhere):  bin/coverage-run.sh [--merge]
#   --merge   add to the existing record instead of replacing it - run once
#             with Pro active and once with it inactive to cover both setups.
# Env:  WP_SMOKE_PATH  WordPress root (default: three levels above the plugin)
#       WPSS_DB_SOCKET MySQL socket for wp-cli when the site's is not the default
#                      (Local by Flywheel), passed as mysqli.default_socket
#
# Installs a temporary mu-plugin that loads tests/coverage/recorder.php for
# HTTP requests, runs every tests/test-*.php with the recorder also required
# on the CLI side, then folds the log into audit/render-coverage.json, which
# bin/coverage-map.php counts as evidence. The loader and log are removed on
# exit, whatever happens.
set -uo pipefail

PLUGIN="$(cd "$(dirname "$0")/.." && pwd)"
WP_ROOT="${WP_SMOKE_PATH:-$(cd "$PLUGIN/../../.." && pwd)}"
WP=( wp )
if [ -n "${WPSS_DB_SOCKET:-}" ]; then
	WP=( php -d "mysqli.default_socket=$WPSS_DB_SOCKET" "$(command -v wp)" )
fi
CONTENT="$WP_ROOT/wp-content"
LOG="$CONTENT/wpss-coverage.log"
SHIM="$CONTENT/mu-plugins/wpss-coverage-recorder.php"
OUT="$PLUGIN/audit/render-coverage.json"
RECORDER="$PLUGIN/tests/coverage/recorder.php"

[ -f "$WP_ROOT/wp-load.php" ] || { echo "Not a WordPress root: $WP_ROOT"; exit 2; }

cleanup() { rm -f "$SHIM" "$LOG"; }
trap cleanup EXIT

: > "$LOG"
mkdir -p "$CONTENT/mu-plugins"
printf "<?php\n// Temporary: written and removed by wp-sell-services/bin/coverage-run.sh.\nrequire '%s';\n" "$RECORDER" > "$SHIM"

FAIL=0
for SCRIPT in "$PLUGIN"/tests/test-*.php; do
	if ! "${WP[@]}" --path="$WP_ROOT" --require="$RECORDER" eval-file "$SCRIPT" > /dev/null 2>&1; then
		echo "check failed: $(basename "$SCRIPT")"
		FAIL=1
	fi
done

php -r '
	[ , $log, $out, $merge, $sha ] = $argv;
	$seen = array();
	if ( $merge && is_readable( $out ) ) {
		foreach ( (array) json_decode( file_get_contents( $out ), true ) as $kind => $names ) {
			if ( is_array( $names ) ) { foreach ( $names as $n ) { $seen[ $kind ][ $n ] = true; } }
		}
	}
	foreach ( file( $log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: array() as $line ) {
		foreach ( (array) json_decode( $line, true ) as $kind => $names ) {
			foreach ( (array) $names as $n ) { $seen[ $kind ][ $n ] = true; }
		}
	}
	$record = array( "_doc" => "What the checks executed, recorded by bin/coverage-run.sh. Evidence for bin/coverage-map.php; regenerate, never edit.", "recorded_at" => gmdate( "c" ), "plugin_sha" => $sha );
	ksort( $seen );
	foreach ( $seen as $kind => $names ) { $names = array_keys( $names ); sort( $names ); $record[ $kind ] = $names; }
	file_put_contents( $out, json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	foreach ( $seen as $kind => $names ) { printf( "%-11s %d executed\n", $kind, count( $names ) ); }
' "$LOG" "$OUT" "$([ "${1:-}" = "--merge" ] && echo 1 || echo 0)" "$(git -C "$PLUGIN" rev-parse --short HEAD)"

exit $FAIL
