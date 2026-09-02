#!/bin/bash
#
# WP Sell Services Test Runner
#
# Usage:
#   ./run-tests.sh           # Run all tests
#   ./run-tests.sh gaps      # Run gap detection only
#   ./run-tests.sh unit      # Run unit tests only
#   ./run-tests.sh integration # Run integration tests
#   ./run-tests.sh rest      # Run REST tests
#
# Works on a fresh clone: dev packages are gitignored and the tracked
# autoloader is the --no-dev build, so this installs the dev packages once and
# swaps the dev autoloader in for the run, restoring the tracked one on exit
# (same dance as bin/phpstan-run). Never run `composer install` by hand and
# leave it there - the pre-commit hook rejects a dev autoloader.
#

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PHPUNIT="$SCRIPT_DIR/vendor/bin/phpunit"
# phpunit.xml declares coverage reports; without --no-coverage every run ends
# "OK, but there were issues!" because Xdebug is not in coverage mode.
PHPUNIT_ARGS="--testdox --no-coverage"

cd "$SCRIPT_DIR"

if [ ! -f "$PHPUNIT" ]; then
    echo "Installing dev packages (one-time, gitignored)..."
    composer install --quiet --no-progress || { echo "composer install failed"; exit 1; }
fi

restore_autoloader() {
    composer dump-autoload --no-dev --quiet 2>/dev/null
    # dump-autoload restamps the root package version/reference from git HEAD,
    # which is the only thing that differs from the tracked file.
    git checkout --quiet -- vendor/composer/installed.php 2>/dev/null
}
trap restore_autoloader EXIT INT TERM
composer dump-autoload --quiet || { echo "could not generate the dev autoloader"; exit 1; }

# Find PHP binary (Local by Flywheel or system)
if [ -f "/Users/$USER/Library/Application Support/Local/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ]; then
    PHP_BIN="/Users/$USER/Library/Application Support/Local/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php"
elif command -v php &> /dev/null; then
    PHP_BIN=$(which php)
else
    echo "PHP not found"
    exit 1
fi

echo "Using PHP: $PHP_BIN"
echo ""

case "${1:-all}" in
    gaps)
        "$PHP_BIN" "$PHPUNIT" tests/Integration/FunctionalityGapTest.php $PHPUNIT_ARGS
        ;;
    unit)
        "$PHP_BIN" "$PHPUNIT" tests/Unit $PHPUNIT_ARGS
        ;;
    integration)
        "$PHP_BIN" "$PHPUNIT" tests/Integration $PHPUNIT_ARGS
        ;;
    rest)
        "$PHP_BIN" "$PHPUNIT" tests/Rest $PHPUNIT_ARGS
        ;;
    all)
        "$PHP_BIN" "$PHPUNIT" $PHPUNIT_ARGS
        ;;
    *)
        # Run specific test by filter
        "$PHP_BIN" "$PHPUNIT" --filter="$1" $PHPUNIT_ARGS
        ;;
esac
