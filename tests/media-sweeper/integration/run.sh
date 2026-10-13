#!/usr/bin/env bash
# Integration test against a real WordPress + MySQL/MariaDB.
# Required env: WP_PATH (WordPress dir), DB_NAME DB_USER DB_PASS (optional DB_HOST).
# Optional env: WP_CLI (default: wp).
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
PLUGIN="$(cd "$HERE/../../../MyContent/plugins/media-sweeper" && pwd)"
WP="${WP_CLI:-wp} --allow-root --path=${WP_PATH}"

[ -f "$WP_PATH/wp-config.php" ] || $WP config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="${DB_HOST:-localhost}" --skip-check
$WP db reset --yes
$WP core install --url=http://localhost --title=MSW --admin_user=admin --admin_password=admin --admin_email=a@b.co --skip-email
rm -rf "$WP_PATH/wp-content/uploads"
ln -sfn "$PLUGIN" "$WP_PATH/wp-content/plugins/media-sweeper"
$WP plugin activate media-sweeper
$WP eval-file "$HERE/scenario.php"
