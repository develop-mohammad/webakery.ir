#!/usr/bin/env bash
# Build the distribution ZIP for one or more plugins at the repo root.
# Every archive gets a top-level <slug>/ folder, which is what the WP uploader expects.
# Usage: bin/build-zip.sh [plugin-slug ...]   (no arguments builds every plugin)
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

command -v zip >/dev/null || die 'zip is not installed'

mapfile -t TARGETS < <(resolve_targets "$@")
[[ ${#TARGETS[@]} -gt 0 ]] || die 'no plugin targets found'

EXCLUDES=(
	'*/.git/*' '*/.github/*' '*/node_modules/*' '*/vendor/*'
	'*/visual-tests/*' '*/tests/*' '*/.DS_Store' '*/.idea/*'
	'*.map' '*/wp-test/*'
)

for slug in "${TARGETS[@]}"; do
	version="$(plugin_version "$slug")"
	log "packaging $slug ${version:+v$version}"
	( cd "$REPO_ROOT" && rm -f "$slug.zip" && zip -qr "$slug.zip" "$slug" -x "${EXCLUDES[@]}" ) || die "failed to package $slug"
	unzip -tq "$REPO_ROOT/$slug.zip" >/dev/null || die "$slug.zip failed integrity check"
	unzip -Z1 "$REPO_ROOT/$slug.zip" | head -n 1 | grep -q "^$slug/" || die "$slug.zip is missing its top-level folder"
	ok "$slug.zip ($(du -h "$REPO_ROOT/$slug.zip" | cut -f1))"
done
