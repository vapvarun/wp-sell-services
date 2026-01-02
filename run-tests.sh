#!/bin/bash
#
# WP Sell Services Test Runner
#
# Usage:
#   ./run-tests.sh           # Run all tests
#   ./run-tests.sh gaps      # Run gap detection only
#   ./run-tests.sh unit      # Run unit tests only
#   ./run-tests.sh integration # Run integration tests
#

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PHPUNIT="$SCRIPT_DIR/vendor/bin/phpunit"

# Check if PHPUnit exists
if [ ! -f "$PHPUNIT" ]; then
    echo "PHPUnit not found. Run: composer install"
    exit 1
fi

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

cd "$SCRIPT_DIR"

case "${1:-all}" in
    gaps)
        "$PHP_BIN" "$PHPUNIT" tests/Integration/FunctionalityGapTest.php --testdox
        ;;
    unit)
        "$PHP_BIN" "$PHPUNIT" tests/Unit --testdox
        ;;
    integration)
        "$PHP_BIN" "$PHPUNIT" tests/Integration --testdox
        ;;
    api)
        "$PHP_BIN" "$PHPUNIT" tests/API --testdox
        ;;
    all)
        "$PHP_BIN" "$PHPUNIT" --testdox
        ;;
    *)
        # Run specific test by filter
        "$PHP_BIN" "$PHPUNIT" --filter="$1" --testdox
        ;;
esac
