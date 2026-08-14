#!/usr/bin/env bash
# Environment setup for the webakery.ir plugin monorepo (used by .cursor/environment.json).
# Installs the PHP QA toolchain, WP-CLI and the local WordPress test site so agents can
# lint and run plugins immediately.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

sudo -n apt-get update -qq || true
sudo -n DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
	php-cli php-mysql php-xml php-mbstring php-zip php-curl php-gd mariadb-server unzip zip rsync curl || true

if ! command -v composer >/dev/null 2>&1; then
	curl -sS -o /tmp/composer-setup.php https://getcomposer.org/installer
	sudo -n php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
fi

if ! command -v wp >/dev/null 2>&1; then
	curl -sSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	chmod +x /tmp/wp-cli.phar
	sudo -n mv /tmp/wp-cli.phar /usr/local/bin/wp
fi

composer install --no-interaction --no-progress

# Pre-warm the throwaway WordPress site; failures here must not break environment setup.
bin/wp-test.sh up || echo 'wp-test site not pre-warmed; run bin/wp-test.sh up manually'

printf '\nready: composer %s | %s | %s\n' \
	"$(composer --version | awk '{print $3}')" "$(wp --version)" "$(php -v | head -n 1)"
