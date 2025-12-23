#!/usr/bin/bash
# please contribute to that run script
# it works for me :-)

set -e

# Check if PHP CLI is available
if ! command -v php &> /dev/null; then
    echo "Error: PHP CLI is not installed or not in PATH"
    echo "Please install php-cli package"
    exit 1
fi

echo "PHP found: $(php -v | head -n 1)"

# Check required PHP modules
REQUIRED_MODULES="sqlite3 pdo_sqlite session json mbstring"
MISSING_MODULES=""

for module in $REQUIRED_MODULES; do
    if ! php -m 2>/dev/null | grep -qi "^${module}$"; then
        MISSING_MODULES="${MISSING_MODULES} ${module}"
    fi
done

if [ -n "$MISSING_MODULES" ]; then
    echo "Error: Missing required PHP modules:${MISSING_MODULES}"
    echo "Please install the corresponding php-* packages"
    exit 1
fi

echo "All required PHP modules are installed"

# Ask for error reporting level
echo ""
echo "Select PHP error reporting level:"
echo "  1) E_ALL (all errors, warnings, notices)"
echo "  2) E_ALL & ~E_NOTICE (errors and warnings, no notices)"
echo "  3) E_ALL & ~E_NOTICE & ~E_DEPRECATED (errors and warnings, no notices/deprecated)"
echo "  4) E_ERROR | E_PARSE (fatal errors only)"
read -p "Your choice [1-4, default=1]: " ERROR_LEVEL_CHOICE

case "$ERROR_LEVEL_CHOICE" in
    2) ERROR_REPORTING="E_ALL & ~E_NOTICE" ;;
    3) ERROR_REPORTING="E_ALL & ~E_NOTICE & ~E_DEPRECATED" ;;
    4) ERROR_REPORTING="E_ERROR | E_PARSE" ;;
    *) ERROR_REPORTING="E_ALL" ;;
esac

# Ask for display_errors
echo ""
read -p "Display PHP errors on web page? [Y/n]: " DISPLAY_ERRORS_CHOICE

case "$DISPLAY_ERRORS_CHOICE" in
    [nN]|[nN][oO]) DISPLAY_ERRORS=0 ;;
    *) DISPLAY_ERRORS=1 ;;
esac

echo ""
echo "Configuration:"
echo "  - Error reporting: ${ERROR_REPORTING}"
echo "  - Display errors: $([ "$DISPLAY_ERRORS" = "1" ] && echo "Yes" || echo "No")"
echo ""

# Start server
if [ -f htdocs/conf/conf.php_sqlite ]; then
    cp htdocs/conf/conf.php_sqlite htdocs/conf/conf.php
    TMPDIR=$(mktemp -d)
    trap "rm -rf ${TMPDIR}" EXIT
    echo "Starting PHP built-in server on http://localhost:8080"
    php -S localhost:8080 -d "display_errors=${DISPLAY_ERRORS}" -d "error_reporting=${ERROR_REPORTING}" -d "session.save_path=${TMPDIR}" -t htdocs/
else
    echo "Error: htdocs/conf/conf.php_sqlite not found"
    exit 1
fi
