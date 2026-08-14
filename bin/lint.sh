#!/usr/bin/env bash
# Lint one or more plugins: PHP syntax, WordPress coding standards, static analysis.
# Usage: bin/lint.sh [--fix] [--quick] [plugin-slug ...]
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

FIX=0
QUICK=0
ARGS=()
for arg in "$@"; do
	case "$arg" in
		--fix) FIX=1 ;;
		--quick) QUICK=1 ;;
		-h|--help) sed -n '2,4p' "$0"; exit 0 ;;
		*) ARGS+=("$arg") ;;
	esac
done

mapfile -t TARGETS < <(resolve_targets "${ARGS[@]+"${ARGS[@]}"}")
[[ ${#TARGETS[@]} -gt 0 ]] || die 'no plugin targets found'

[[ -x "$REPO_ROOT/vendor/bin/phpcs" ]] || die 'run "composer install" first (vendor/bin/phpcs missing)'

STATUS=0

for slug in "${TARGETS[@]}"; do
	log "linting $slug"
	dir="$REPO_ROOT/$slug"

	syntax_failed=0
	while IFS= read -r -d '' file; do
		if ! php -l "$file" >/dev/null; then
			php -l "$file" || true
			syntax_failed=1
		fi
	done < <(find "$dir" -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0)
	if [[ $syntax_failed -eq 1 ]]; then
		STATUS=1
		warn "$slug: PHP syntax errors"
		continue
	fi
	ok "$slug: PHP syntax"

	if [[ $FIX -eq 1 ]]; then
		"$REPO_ROOT/vendor/bin/phpcbf" --standard="$REPO_ROOT/phpcs.xml.dist" "$dir" || true
	fi

	cs_args=(--standard="$REPO_ROOT/phpcs.xml.dist" --report=summary --runtime-set text_domain "$slug,default")
	[[ $QUICK -eq 1 ]] && cs_args+=(--warning-severity=0)
	if "$REPO_ROOT/vendor/bin/phpcs" "${cs_args[@]}" "$dir"; then
		ok "$slug: coding standards"
	else
		STATUS=1
		warn "$slug: coding standards issues (run: bin/lint.sh --fix $slug, then fix the rest by hand)"
	fi

	if [[ $QUICK -eq 0 ]]; then
		# Plugin constants are defined at runtime in the bootstrap, so hand PHPStan a stub
		# declaring them instead of letting every usage report constant.notFound. They all get the
		# plugin directory as their value so that require_once "<PREFIX>_PATH . ..." still resolves.
		stub="$(mktemp -t phpstan-constants-XXXXXX.php)"
		{
			printf '<?php\n'
			grep -rhoP "define\(\s*'\K[A-Z0-9_]+" "$dir" --include='*.php' | sort -u |
				while IFS= read -r constant; do
					printf "defined( '%s' ) || define( '%s', '%s/' );\n" "$constant" "$constant" "$dir"
				done
		} > "$stub"

		if "$REPO_ROOT/vendor/bin/phpstan" analyse --configuration="$REPO_ROOT/phpstan.neon.dist" \
			--autoload-file="$stub" --memory-limit=1G --no-progress "$dir"; then
			ok "$slug: static analysis"
		else
			STATUS=1
			warn "$slug: static analysis issues"
		fi
		rm -f "$stub"
	fi
done

[[ $STATUS -eq 0 ]] && log 'all checks passed' || log 'some checks failed'
exit $STATUS
