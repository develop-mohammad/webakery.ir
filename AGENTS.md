# AGENTS.md

## Cursor Cloud specific instructions

### What this repo is
A monorepo of **webakery.ir** PHP deliverables (all UI text is Persian / RTL). There is **no build system, no package manager, and no automated test/lint config** (`no composer.json` / `package.json` / lockfiles). See `README.md` for the product catalog and shortcodes. Two kinds of deliverables live here:

- **WordPress plugins** (plain PHP, run inside WordPress): `nobat-man/` (appointment booking, needs WooCommerce), `hesabdar/` (WooCommerce orders/invoices/accounting, needs WooCommerce), `baget/` (WooCommerce checkout field editor, needs WooCommerce), `access-levels/` (user access control), `webakery-font-swap/`, `webakery-quiet-notices/`. The root `*.zip` files are just zipped copies of these folders (the shipped artifacts).
- **License Server** (`license-server/`): a standalone PHP web app (license API, admin panel, customer portal, Zibal payment, plugin auto-updates). **It uses JSON flat-file storage (`data/licenses.json`) — no MySQL.** The `LS_DB_*` constants in `config.php` are dead placeholders. Do not commit changes to `license-server/data/licenses.json` (it holds real-looking license data; use throwaway data and `git checkout` it if a test writes to it).

### How the dev environment is wired (already set up in the snapshot)
A full WordPress install lives **outside the repo** at `/home/ubuntu/wordpress`, backed by local MariaDB, with **WooCommerce** installed. The six plugins are **symlinked** into it, so edits under `/workspace/<plugin>` take effect immediately (no reinstall/copy):

- `wp-content/plugins/<plugin> -> /workspace/<plugin>` for each of the six plugins above.

Stack: PHP 8.3 CLI (`mysqli`/`gd`/`curl`/`mbstring`/`xml`/`zip`/`intl`/`bcmath`), MariaDB 10.11, and `wp-cli` (`wp`). DB: database `wordpress`, user `wp` / pass `wp` on `127.0.0.1`. WP admin: `admin` / `admin123`.

### Starting the services (do this each session; NOT in the update script)
Nothing auto-starts. MariaDB and both PHP servers run in tmux (`tmux -f /exec-daemon/tmux.portal.conf ls`).

```bash
# 1) MariaDB (data dir persists in the snapshot)
sudo mysqld_safe --datadir=/var/lib/mysql >/tmp/mysqld.log 2>&1 &
sleep 8 && sudo mariadb-admin ping

# 2) WordPress dev server (custom router required — see gotcha)
php -S 0.0.0.0:8080 -t /home/ubuntu/wordpress /home/ubuntu/wp-router.php >/tmp/wp-server.log 2>&1 &

# 3) License server (standalone; JSON storage; no DB needed)
php -S 0.0.0.0:8090 -t /workspace/license-server >/tmp/license-server.log 2>&1 &
```

WordPress: http://localhost:8080  •  Admin: http://localhost:8080/wp-admin/ (`admin`/`admin123`).
License server: http://localhost:8090 — API `/api/?action=ping`, admin `/admin/`, portal `/portal/`, pay `/pay/?plugin=wccp`. Admin login default is `admin` / `change-this-password` (from `config.php`; may be overridden by `data/admin-auth.json`).

### Non-obvious gotchas
- **`php -S` needs a router for WordPress.** `/home/ubuntu/wp-router.php` routes pretty permalinks to `index.php` and, crucially, serves the **symlinked** plugin CSS/JS by path. Because the plugins are symlinks pointing outside the docroot, a naive `realpath()` router (or WP default handling) makes plugin assets 301-redirect instead of loading, which silently breaks AJAX-based plugin forms. Do not "simplify" the router back to `realpath()`.
- **Hesabdar invoices**: viewed from the Hesabdar Orders page (`wp-admin/admin.php?page=wci-orders`), invoice/`فاکتور` action per order. Invoice serves as an HTML file (no PDF library — "save as PDF" is via the browser print dialog). A non-zero total needs a WooCommerce order with line items priced.
- **Nobat Man / Hesabdar / Baget require WooCommerce active** (`class_exists('WooCommerce')` guards). WooCommerce is already installed & active in the snapshot.
- **License activation & Zibal payment** phone home to external services (`webakery.ir`, Zibal, Google OAuth). Those external calls won't succeed offline, but the license server's local API/admin/portal/pay pages render and the JSON datastore works without them.

### Lint / test / build
None configured in-repo — there is no build step and no test suite. For a quick PHP syntax check of a single file: `php -l <file>`.
