#!/usr/bin/env bash
# Shared helpers for the webakery.ir plugin toolkit. Source this, do not run it.

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export REPO_ROOT

# Directories that live in the monorepo but are not WordPress plugins.
NON_PLUGIN_DIRS=(bin license-server license-server-updates vendor node_modules wp-test webakery-unused-assets .cursor .git)

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m  ok\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m  !!\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

is_non_plugin_dir() {
	local candidate="$1" skip
	for skip in "${NON_PLUGIN_DIRS[@]}"; do
		[[ "$candidate" == "$skip" ]] && return 0
	done
	return 1
}

# Echo every plugin slug in the repo (a directory holding <slug>.php with a Plugin Name header).
plugin_slugs() {
	local dir slug
	for dir in "$REPO_ROOT"/*/; do
		slug="$(basename "$dir")"
		is_non_plugin_dir "$slug" && continue
		[[ -f "$dir$slug.php" ]] || continue
		grep -q 'Plugin Name:' "$dir$slug.php" || continue
		printf '%s\n' "$slug"
	done
}

# Normalise arguments into plugin slugs; with no arguments, return every plugin.
resolve_targets() {
	local arg slug
	if [[ $# -eq 0 ]]; then
		plugin_slugs
		return
	fi
	for arg in "$@"; do
		slug="$(basename "${arg%/}")"
		[[ -d "$REPO_ROOT/$slug" ]] || die "plugin directory not found: $slug"
		printf '%s\n' "$slug"
	done
}

plugin_version() {
	local slug="$1"
	grep -m1 -oP '^\s*\*\s*Version:\s*\K[0-9a-zA-Z.\-]+' "$REPO_ROOT/$slug/$slug.php" 2>/dev/null || echo ''
}
