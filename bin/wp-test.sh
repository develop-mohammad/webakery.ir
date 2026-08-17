#!/usr/bin/env bash
# Disposable WordPress site for verifying plugins for real (MariaDB + WP-CLI + PHP built-in server).
# The site lives in ./wp-test, which is git-ignored.
#
# Usage:
#   bin/wp-test.sh up                    # start MariaDB, install WordPress if needed
#   bin/wp-test.sh install <slug ...>    # copy plugin(s) into the site and activate them
#   bin/wp-test.sh sync <slug ...>       # re-copy plugin files after editing (no activation)
#   bin/wp-test.sh wp <wp-cli args ...>  # run WP-CLI against the site
#   bin/wp-test.sh eval-file <file>      # run a PHP file inside WordPress (smoke tests)
#   bin/wp-test.sh serve [port]          # start the site on http://127.0.0.1:<port> (default 8888)
#   bin/wp-test.sh stop                  # stop the web server
#   bin/wp-test.sh destroy               # delete the site and its database
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

WP_DIR="$REPO_ROOT/wp-test"
DB_NAME='webakery_test'
DB_USER='webakery'
DB_PASS='webakery'
# TCP rather than the unix socket: the PHP CLI here looks for a socket path MariaDB does not use.
DB_HOST='127.0.0.1'
SITE_PORT="${WP_TEST_PORT:-8888}"
ADMIN_USER='admin'
ADMIN_PASS='admin'
SERVER_PID_FILE="$WP_DIR/.server.pid"

wp_cli() { wp --path="$WP_DIR" "$@"; }

start_db() {
	if ! sudo -n mysqladmin ping >/dev/null 2>&1; then
		log 'starting MariaDB'
		sudo -n mkdir -p /run/mysqld && sudo -n chown mysql:mysql /run/mysqld
		sudo -n /etc/init.d/mariadb start >/dev/null 2>&1 || die 'could not start MariaDB'
		for _ in $(seq 1 30); do
			sudo -n mysqladmin ping >/dev/null 2>&1 && break
			sleep 1
		done
	fi
	sudo -n mysqladmin ping >/dev/null 2>&1 || die 'MariaDB is not responding'
	sudo -n mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARACTER SET utf8mb4;
		CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
		GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
		FLUSH PRIVILEGES;" || die 'could not provision the test database'
	ok 'database ready'
}

cmd_up() {
	start_db
	mkdir -p "$WP_DIR"
	if [[ ! -f "$WP_DIR/wp-load.php" ]]; then
		log 'downloading WordPress'
		# Bundled themes are included on purpose: without an active theme the front end renders
		# an empty response, which makes browser testing of shortcodes and blocks impossible.
		wp core download --path="$WP_DIR" --force >/dev/null || die 'wp core download failed'
	fi
	if [[ ! -f "$WP_DIR/wp-config.php" ]]; then
		wp config create --path="$WP_DIR" --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" \
			--dbhost="$DB_HOST" --force --extra-php <<'PHP' >/dev/null || die 'wp config create failed'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true );
PHP
	fi
	if ! wp_cli core is-installed >/dev/null 2>&1; then
		log 'installing WordPress'
		wp_cli core install --url="http://127.0.0.1:$SITE_PORT" --title='Webakery Test' \
			--admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email='dev@webakery.ir' \
			--skip-email >/dev/null || die 'wp core install failed'
		wp_cli rewrite structure '/%postname%/' >/dev/null
		wp_cli option update timezone_string 'Asia/Tehran' >/dev/null
	fi
	if [[ -z "$( wp_cli theme list --status=active --field=name 2>/dev/null )" ]]; then
		theme="$( wp_cli theme list --field=name 2>/dev/null | head -n 1 )"
		[[ -n "$theme" ]] && wp_cli theme activate "$theme" >/dev/null
	fi
	ok "WordPress $(wp_cli core version) ready at $WP_DIR (admin/admin)"
}

cmd_sync() {
	[[ $# -gt 0 ]] || die 'usage: bin/wp-test.sh sync <slug ...>'
	[[ -f "$WP_DIR/wp-load.php" ]] || die 'run "bin/wp-test.sh up" first'
	mapfile -t slugs < <(resolve_targets "$@")
	mkdir -p "$WP_DIR/wp-content/plugins"
	for slug in "${slugs[@]}"; do
		rsync -a --delete --exclude '.git' --exclude 'node_modules' --exclude 'visual-tests' \
			"$REPO_ROOT/$slug/" "$WP_DIR/wp-content/plugins/$slug/" || die "could not sync $slug"
		ok "synced $slug"
	done
}

cmd_install() {
	cmd_up
	cmd_sync "$@"
	mapfile -t slugs < <(resolve_targets "$@")
	for slug in "${slugs[@]}"; do
		wp_cli plugin activate "$slug" || die "could not activate $slug"
	done
	wp_cli plugin list --status=active --fields=name,version,status
}

cmd_serve() {
	[[ -n "${1:-}" ]] && SITE_PORT="$1"
	[[ -f "$WP_DIR/wp-load.php" ]] || die 'run "bin/wp-test.sh up" first'
	cmd_stop
	wp_cli option update siteurl "http://127.0.0.1:$SITE_PORT" >/dev/null
	wp_cli option update home "http://127.0.0.1:$SITE_PORT" >/dev/null
	nohup php -S "127.0.0.1:$SITE_PORT" -t "$WP_DIR" >"$WP_DIR/server.log" 2>&1 &
	echo $! > "$SERVER_PID_FILE"
	sleep 2
	curl -sS -o /dev/null -w 'HTTP %{http_code}\n' "http://127.0.0.1:$SITE_PORT/" || warn 'site did not respond yet'
	ok "serving http://127.0.0.1:$SITE_PORT (wp-admin: admin/admin)"
}

cmd_stop() {
	if [[ -f "$SERVER_PID_FILE" ]]; then
		local pid
		pid="$(cat "$SERVER_PID_FILE")"
		if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
			kill "$pid" && ok "stopped web server (pid $pid)"
		fi
		rm -f "$SERVER_PID_FILE"
	fi
}

cmd_destroy() {
	cmd_stop
	sudo -n mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" >/dev/null 2>&1
	rm -rf "$WP_DIR"
	ok 'test site removed'
}

COMMAND="${1:-}"
[[ $# -gt 0 ]] && shift
case "$COMMAND" in
	up) cmd_up ;;
	install) cmd_install "$@" ;;
	sync) cmd_sync "$@" ;;
	wp) wp_cli "$@" ;;
	eval-file) [[ -f "${1:-}" ]] || die 'usage: bin/wp-test.sh eval-file <file>'; wp_cli eval-file "$1" ;;
	serve) cmd_serve "${1:-}" ;;
	stop) cmd_stop ;;
	destroy) cmd_destroy ;;
	*) sed -n '2,16p' "$0"; exit 1 ;;
esac
