#!/usr/bin/env bash
# Scaffold a new webakery.ir WordPress plugin that already follows the repo conventions:
# slug-named bootstrap, prefixed classes, index.php silencers, Persian RTL readme, uninstall handler.
#
# Usage:
#   bin/new-plugin.sh <slug> [--prefix WBX] [--name "نام فارسی | English"] [--desc "..."] [--license]
#
# Example:
#   bin/new-plugin.sh webakery-gallery --prefix WBG --name "گالری | Gallery" --license
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

SLUG="${1:-}"
[[ -n "$SLUG" ]] || die 'usage: bin/new-plugin.sh <slug> [--prefix WBX] [--name "..."] [--desc "..."] [--license]'
shift
[[ "$SLUG" =~ ^[a-z0-9]+(-[a-z0-9]+)*$ ]] || die 'slug must be lowercase kebab-case, e.g. webakery-gallery'
[[ -e "$REPO_ROOT/$SLUG" ]] && die "$SLUG already exists"

PREFIX=''
NAME=''
DESC='افزونه وردپرس webakery.ir'
WITH_LICENSE=0
while [[ $# -gt 0 ]]; do
	case "$1" in
		--prefix) PREFIX="${2:-}"; shift 2 ;;
		--name) NAME="${2:-}"; shift 2 ;;
		--desc) DESC="${2:-}"; shift 2 ;;
		--license) WITH_LICENSE=1; shift ;;
		*) die "unknown option: $1" ;;
	esac
done

# Default prefix: first letter of each slug segment, e.g. webakery-gallery -> WG.
if [[ -z "$PREFIX" ]]; then
	PREFIX="$(printf '%s' "$SLUG" | awk -F- '{ for (i = 1; i <= NF; i++) printf toupper(substr($i, 1, 1)) }')"
fi
PREFIX="$(printf '%s' "$PREFIX" | tr '[:lower:]' '[:upper:]' | tr -cd 'A-Z0-9')"
[[ -n "$PREFIX" ]] || die 'could not derive a class prefix; pass --prefix'
LOWER_PREFIX="$(printf '%s' "$PREFIX" | tr '[:upper:]' '[:lower:]')"
[[ -n "$NAME" ]] || NAME="$SLUG"

PLUGIN_DIR="$REPO_ROOT/$SLUG"
mkdir -p "$PLUGIN_DIR"/{includes,assets/css,assets/js,admin/css,admin/js,templates,languages}

silence() {
	printf '<?php\n// Silence is golden.\n' > "$1/index.php"
}
silence "$PLUGIN_DIR"
for sub in includes assets assets/css assets/js admin admin/css admin/js templates languages; do
	silence "$PLUGIN_DIR/$sub"
done

LICENSE_BLOCK_FILE="$(mktemp -t license-block-XXXXXX)"
trap 'rm -f "$LICENSE_BLOCK_FILE"' EXIT

if [[ $WITH_LICENSE -eq 1 ]]; then
	source_license=''
	for candidate in access-levels webakery-login webakery-chat-box nobat-man; do
		if [[ -f "$REPO_ROOT/$candidate/includes/class-wb-license.php" ]]; then
			source_license="$REPO_ROOT/$candidate/includes/class-wb-license.php"
			break
		fi
	done
	[[ -n "$source_license" ]] || die 'no existing class-wb-license.php found to copy'
	cp "$source_license" "$PLUGIN_DIR/includes/class-wb-license.php"
	cat > "$LICENSE_BLOCK_FILE" <<PHP
		require_once ${PREFIX}_PATH . 'includes/class-wb-license.php';
		WB_License::init(
			array(
				'product' => ${PREFIX}_PRODUCT,
				'name'    => '${NAME}',
				'file'    => ${PREFIX}_FILE,
				'version' => ${PREFIX}_VERSION,
				'page'    => 'admin.php?page=${SLUG}&tab=license',
			)
		);
PHP
fi

cat > "$PLUGIN_DIR/$SLUG.php" <<PHP
<?php
/**
 * Plugin Name: ${NAME}
 * Description: ${DESC}
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: ${SLUG}
 * Domain Path: /languages
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( '${PREFIX}_LOADED' ) ) {
	return;
}
define( '${PREFIX}_LOADED', true );
define( '${PREFIX}_VERSION', '1.0.0' );
define( '${PREFIX}_FILE', __FILE__ );
define( '${PREFIX}_PATH', plugin_dir_path( __FILE__ ) );
define( '${PREFIX}_URL', plugin_dir_url( __FILE__ ) );
define( '${PREFIX}_PRODUCT', '${SLUG}' );

require_once ${PREFIX}_PATH . 'includes/class-${LOWER_PREFIX}-plugin.php';

register_activation_hook( __FILE__, array( '${PREFIX}_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( '${PREFIX}_Plugin', 'instance' ), 5 );
PHP

cat > "$PLUGIN_DIR/includes/class-${LOWER_PREFIX}-plugin.php" <<PHP
<?php
defined( 'ABSPATH' ) || exit;

class ${PREFIX}_Plugin {

	/** @var self|null */
	private static \$instance = null;

	public static function instance() {
		if ( null === self::\$instance ) {
			self::\$instance = new self();
		}
		return self::\$instance;
	}

	public static function activate() {
		if ( ! get_option( '${LOWER_PREFIX}_settings' ) ) {
			update_option( '${LOWER_PREFIX}_settings', array(), false );
		}
	}

	private function __construct() {
		load_plugin_textdomain( '${SLUG}', false, dirname( plugin_basename( ${PREFIX}_FILE ) ) . '/languages' );
%%LICENSE%%
		if ( is_admin() ) {
			add_action( 'admin_menu', array( \$this, 'admin_menu' ) );
		}
	}

	public function admin_menu() {
		add_menu_page(
			__( '${NAME}', '${SLUG}' ),
			__( '${NAME}', '${SLUG}' ),
			'manage_options',
			'${SLUG}',
			array( \$this, 'render_admin_page' ),
			'dashicons-admin-generic',
			58
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap" dir="rtl"><h1>' . esc_html__( '${NAME}', '${SLUG}' ) . '</h1></div>';
	}
}
PHP

# Splice in the license bootstrap, or drop the placeholder line entirely.
awk -v block="$LICENSE_BLOCK_FILE" '
	/^%%LICENSE%%$/ {
		while ( ( getline line < block ) > 0 ) { print line }
		next
	}
	{ print }
' "$PLUGIN_DIR/includes/class-${LOWER_PREFIX}-plugin.php" > "$PLUGIN_DIR/includes/.class.tmp" &&
	mv "$PLUGIN_DIR/includes/.class.tmp" "$PLUGIN_DIR/includes/class-${LOWER_PREFIX}-plugin.php"

cat > "$PLUGIN_DIR/uninstall.php" <<PHP
<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( '${LOWER_PREFIX}_settings' );
PHP

cat > "$PLUGIN_DIR/readme.txt" <<TXT
=== ${NAME} ===
Contributors: webakery.ir
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

${DESC}

== Installation ==

1. پوشه \`${SLUG}\` را در \`wp-content/plugins/\` قرار دهید یا ZIP را از پیشخوان بارگذاری کنید.
2. افزونه را فعال کنید.

== Changelog ==

= 1.0.0 =
* انتشار اولیه.
TXT

printf 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n"X-Domain: %s\\n"\n' "$SLUG" > "$PLUGIN_DIR/languages/$SLUG.pot"

log "scaffolded $SLUG (prefix ${PREFIX}_)"
ok "next: bin/lint.sh $SLUG"
ok "then: bin/wp-test.sh install $SLUG"
ok "ship: bin/build-zip.sh $SLUG"
