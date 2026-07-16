# AGENTS.md

## Cursor Cloud specific instructions

### What this repo is
This repo is **not a standalone app** — it holds three WordPress-ecosystem deliverables (all UI text is Persian/RTL):

- `hesabdar/` — WordPress plugin: products, orders, and printable invoices (the primary product).
- `webakery-speed/` — WordPress plugin: fetches Google PageSpeed/Lighthouse data and applies safe perf fixes.
- `webakery-speed-chrome/` — a standalone Chrome (Manifest V3) extension, **not** a WordPress plugin.

There is **no build system, no package manager, and no automated tests** (no `composer.json` / `package.json` / lockfiles). The plugins are plain PHP that runs inside WordPress; "running" them means loading them in a WordPress install. See `README.md` for the standard install/shortcode reference.

### How the dev environment is wired (already set up in the snapshot)
A full WordPress 6.7 install lives **outside the repo** at `/home/ubuntu/wordpress`, backed by a local MariaDB. The two plugins are **symlinked** into it, so edits under `/workspace/hesabdar` and `/workspace/webakery-speed` take effect immediately (no reinstall/copy needed):

- `wp-content/plugins/hesabdar -> /workspace/hesabdar`
- `wp-content/plugins/webakery-speed -> /workspace/webakery-speed`

Stack: PHP 8.3 CLI + `mysqli`/`gd`/`curl`/`mbstring`/`xml`/`zip`, MariaDB, and `wp-cli` (`wp`). DB: database `wordpress`, user `wp` / password `wp` on `127.0.0.1`. WP admin: `admin` / `admin123`.

### Starting the services (do this each session; NOT in the update script)
Neither MariaDB nor the web server auto-start. Run:

```bash
# 1) Start MariaDB (data dir persists in the snapshot)
sudo mysqld_safe --datadir=/var/lib/mysql >/tmp/mysqld.log 2>&1 &
sleep 8 && sudo mysqladmin ping

# 2) Start the WordPress dev server (uses a custom router — see gotcha below)
cd /home/ubuntu/wordpress
setsid php -S 0.0.0.0:8080 -t /home/ubuntu/wordpress /home/ubuntu/wp-router.php \
  >/tmp/wp-server.log 2>&1 < /dev/null & disown
```

Site: http://localhost:8080  •  Admin: http://localhost:8080/wp-admin/ (`admin` / `admin123`).
Demo pages already exist: `/order/` (`[hesabdar_order]` form) and `/products/` (`[hesabdar_products]`).

### Non-obvious gotchas
- **`php -S` needs a router for WordPress.** `/home/ubuntu/wp-router.php` routes pretty permalinks to `index.php`. Crucially, because the plugins are **symlinks pointing outside the docroot**, a naive `realpath()`-based router (or WordPress's default handling) makes plugin CSS/JS 301-redirect instead of loading — which silently breaks the AJAX order form (it falls back to a broken GET submit). The router serves symlinked static assets directly by path; do not "simplify" it back to `realpath()`.
- **Order form is AJAX.** `[hesabdar_order]` posts to `wp-admin/admin-ajax.php` (`action=hesabdar_submit_order`) with a nonce; if `hesabdar/public/js/hesabdar.js` doesn't load (see above), submission appears to fail.
- **Invoices** are viewed from the admin Orders list (فاکتور column → مشاهده/دانلود) and "saved as PDF" via the browser print dialog — there is no PDF library. Invoice total = product unit price × quantity, so a product with a `_hsb_price` must exist for a non-zero total.
- **Webakery Speed live scans** call the Google PageSpeed API and need a Google API key entered in the plugin settings (optional). The paste-JSON and `pagespeed.web.dev` URL-import paths work without a key.

### Lint / test / build
None configured in-repo. There is no build step and no test suite. Optionally, `php -l <file>` lint-checks a single PHP file.
