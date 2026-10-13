=== Media Sweeper Pro ===
Contributors: mediasweeper
Tags: media, unused images, webp, cleanup, seo
Requires at least: 5.3
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later

Find unused images/videos/audio, preview them in bulk, quarantine or delete them, remove orphan files, merge duplicates,
convert JPG/PNG to WebP with safe reference rewriting, and fix image SEO (alt text, file names, 301 redirects).

== Description ==

* Index-based scan of posts, post meta (Elementor, WooCommerce, ACF), options, widgets, term/user meta, plugin tables and theme files.
* Bulk preview grid with lightbox (video/audio playable), filters, CSV export.
* Reversible quarantine, protection rules, orphan-file and duplicate detection (+ safe merge).
* JPG/PNG -> WebP: verified output, byte-exact rewriting of HTML / JSON / serialized data, retention of originals, revert.
* Oversized-original optimizer, broken-link report, alt/file-name audit with rename + 301 redirects.
* Printable client report, scan history chart, scheduled scans with e-mail, WP-CLI (`wp msw ...`).

The interface is written in Persian and ships with an English catalog (`languages/en_US.php`). Persian (fa*) locales show the original text; every other locale uses `languages/<locale>.php` if present, otherwise English. To add a language, copy `en_US.php` to `languages/<locale>.php` and translate the values.

== Installation ==
1. Upload the `media-sweeper` folder to `/wp-content/plugins/` (or use `tools/build-zip.sh` to build a zip).
2. Activate, then open Media > Media Sweeper and run a scan.

== Safety features ==
* Only relative URLs, this site's hosts and your configured CDN domains are rewritten; links to other sites stay untouched.
* Offloaded/CDN attachments and attachments that share a file (multilingual duplicates) are never moved, renamed, converted or deleted by the plugin.
* Quarantined files get random names; the dashboard probes whether the folder is web-accessible and shows an Nginx rule if needed.
* Rename and image shrinking keep the previous files for a retention period (default 7 days) and can be reverted; duplicate merges are reversible from quarantine.
* "Preview" shows which database rows a rename, merge or WebP conversion would change before anything is applied.

== Important ==
* Take a full backup (files + database) before large operations; try on staging first.
* Quarantine, WebP conversion, renames and image shrinking are reversible within their retention period; permanent deletion is not.
* The quarantine and backup folders are protected with .htaccess (Apache). On Nginx the dashboard warns and shows the rule to add.

== Testing ==
`tests/` in the repository: standalone unit tests, an end-to-end scenario (`tests/media-sweeper/integration/run.sh`) and a GitHub Actions workflow.
