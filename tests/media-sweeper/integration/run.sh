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
if [ "${MULTISITE:-0}" = "1" ]; then
  # Run the whole scenario on sub-site #2 (uploads in uploads/sites/2, prefixed tables).
  $WP core multisite-convert --title=MSW
  $WP plugin activate media-sweeper --network
  $WP site create --slug=s2 --title=S2 --email=a@b.co
  $WP --url=http://localhost/s2 eval-file "$HERE/scenario.php"
else
  $WP plugin activate media-sweeper
  $WP eval-file "$HERE/scenario.php"
fi
