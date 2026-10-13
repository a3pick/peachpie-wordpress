#!/usr/bin/env bash
# HTTP-level checks (needs run.sh to have installed WordPress): admin-ajax auth + upload 301 redirect.
# Env: WP_PATH, WP_CLI (default wp), PORT (default 8081)
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
WP="${WP_CLI:-wp} --allow-root --path=${WP_PATH}"
PORT="${PORT:-8081}"; B="http://127.0.0.1:$PORT"
FAILS=0; ok() { if [ "$2" = "1" ]; then echo "ok   $1"; else echo "FAIL $1"; FAILS=$((FAILS+1)); fi; }

$WP option update home "$B" >/dev/null; $WP option update siteurl "$B" >/dev/null
$WP user update admin --user_pass=admin >/dev/null
$WP user create editor1 e1@x.co --role=editor --user_pass=editor1 >/dev/null 2>&1 || true

# Router: serve real files, send everything else to WordPress (like Apache/Nginx rewrite rules).
cat > /tmp/msw-router.php <<'PHP'
<?php
$root = $_SERVER['DOCUMENT_ROOT']; $path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ); $file = $root . $path;
if ( is_file( $file ) && ! preg_match( '/\.php$/', $file ) ) { return false; }
if ( is_file( $file ) ) { chdir( dirname( $file ) ); $_SERVER['SCRIPT_NAME'] = $path; require $file; return true; }
chdir( $root ); $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php'; require $root . '/index.php';
PHP
php -S "127.0.0.1:$PORT" -t "$WP_PATH" /tmp/msw-router.php >/tmp/msw-server.log 2>&1 &
SERVER=$!; trap 'kill $SERVER 2>/dev/null || true' EXIT; sleep 2

login() { rm -f "$1"; curl -s -c "$1" -b "$1" -o /dev/null -d "log=$2&pwd=$3&wp-submit=Log+In&testcookie=1" -H "Cookie: wordpress_test_cookie=WP%20Cookie%20check" "$B/wp-login.php"; }
login /tmp/msw-admin.jar admin admin; login /tmp/msw-editor.jar editor1 editor1
NONCE=$(curl -s -b /tmp/msw-admin.jar "$B/wp-admin/upload.php?page=media-sweeper" | grep -o 'var MSW = {[^;]*' | grep -o '"nonce":"[a-z0-9]*"' | cut -d'"' -f4)
[ -n "$NONCE" ] && ok "admin gets the plugin page + nonce" 1 || ok "admin gets the plugin page + nonce" 0
ajax() { curl -s -b "$1" -X POST "$B/wp-admin/admin-ajax.php" -d "action=msw&nonce=$2" "${@:3}"; }
ajax /tmp/msw-admin.jar "$NONCE" -d do=dashboard | grep -q '"success":true' && ok "dashboard JSON for admin" 1 || ok "dashboard JSON for admin" 0
[ "$(curl -s -b /tmp/msw-admin.jar -X POST "$B/wp-admin/admin-ajax.php" -d 'action=msw&do=dashboard')" = "-1" ] && ok "missing nonce rejected" 1 || ok "missing nonce rejected" 0
[ "$(ajax /tmp/msw-editor.jar "$NONCE" -d do=dashboard)" = "-1" ] && ok "editor with a stolen nonce rejected" 1 || ok "editor with a stolen nonce rejected" 0
[ "$(curl -s -b /tmp/msw-editor.jar -o /dev/null -w '%{http_code}' "$B/wp-admin/upload.php?page=media-sweeper")" = "403" ] && ok "editor cannot open the page" 1 || ok "editor cannot open the page" 0
ajax /tmp/msw-admin.jar "$NONCE" -d do=list -d tab=unused --data-urlencode "f[q]=' OR 1=1 -- " --data-urlencode 'f[orderby]=bytes;DROP TABLE x' | grep -q '"success":true' && ok "hostile filter values are harmless" 1 || ok "hostile filter values are harmless" 0

# 301 for a renamed upload: create an image, rename it with the plugin, request the old URL.
$WP eval-file "$HERE/http_fixture.php" >/tmp/msw-fixture.txt
OLD=$(sed -n 1p /tmp/msw-fixture.txt); NEW=$(sed -n 2p /tmp/msw-fixture.txt)
HDR=$(curl -s -o /dev/null -D - "$B/wp-content/uploads/$OLD")
echo "$HDR" | grep -q "301" && echo "$HDR" | grep -qi "X-Redirect-By: Media Sweeper" && ok "old upload URL answers 301 from Media Sweeper" 1 || ok "old upload URL answers 301 from Media Sweeper" 0
echo "$HDR" | grep -qi "Location: .*$NEW" && ok "redirect points to the new file" 1 || ok "redirect points to the new file" 0
[ "$(curl -s -o /dev/null -w '%{http_code}' "$B/wp-content/uploads/$NEW")" = "200" ] && ok "new file is served directly" 1 || ok "new file is served directly" 0
[ "$FAILS" = "0" ] && echo "ALL PASSED" || { echo "$FAILS FAILED"; exit 1; }
