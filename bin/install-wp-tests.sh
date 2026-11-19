#!/usr/bin/env bash
# Installs the WordPress and WooCommerce files used by the PHPUnit integration suite.
set -euo pipefail

if [ "$#" -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4:-127.0.0.1}

ROOT=$(cd "$(dirname "$0")/.." && pwd)
TMPDIR=${TMPDIR:-/tmp}
TMPDIR=${TMPDIR%/}
WP_CORE_DIR=${WP_CORE_DIR:-$TMPDIR/ean-to-pos-wordpress}
WP_VERSION=${5:-}

if [ -z "$WP_VERSION" ]; then
	WP_VERSION=$(php -r 'echo (require $argv[1])["versions"]["wp-phpunit/wp-phpunit"]["pretty_version"];' "$ROOT/vendor/composer/installed.php")
	WP_VERSION=${WP_VERSION#v}
fi

install_core() {
	if [ -f "$WP_CORE_DIR/wp-load.php" ]; then
		return
	fi

	extract=$(mktemp -d)
	curl -fsSL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" -o "$extract/wordpress.tar.gz"
	tar -xzf "$extract/wordpress.tar.gz" -C "$extract"
	rm -rf "$WP_CORE_DIR"
	mv "$extract/wordpress" "$WP_CORE_DIR"
	rm -rf "$extract"
}

install_woocommerce() {
	if [ -f "$WP_CORE_DIR/wp-content/plugins/woocommerce/woocommerce.php" ]; then
		return
	fi

	archive="$TMPDIR/ean-to-pos-woocommerce.zip"
	curl -fsSL "https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip" -o "$archive"
	unzip -qo "$archive" -d "$WP_CORE_DIR/wp-content/plugins"
}

install_config() {
	php -r '
		[$name, $user, $pass, $host, $core, $dest] = array_slice($argv, 1);
		if (!preg_match("/^[A-Za-z0-9_]+$/", $name)) {
			fwrite(STDERR, "Invalid database name.\n");
			exit(1);
		}
		$config = "<?php\n";
		$config .= "define( \"ABSPATH\", " . var_export(rtrim($core, "/") . "/", true) . " );\n";
		$config .= "define( \"DB_NAME\", " . var_export($name, true) . " );\n";
		$config .= "define( \"DB_USER\", " . var_export($user, true) . " );\n";
		$config .= "define( \"DB_PASSWORD\", " . var_export($pass, true) . " );\n";
		$config .= "define( \"DB_HOST\", " . var_export($host, true) . " );\n";
		$config .= "define( \"DB_CHARSET\", \"utf8\" );\n";
		$config .= "define( \"DB_COLLATE\", \"\" );\n";
		$config .= "define( \"WP_TESTS_DOMAIN\", \"example.org\" );\n";
		$config .= "define( \"WP_TESTS_EMAIL\", \"admin@example.org\" );\n";
		$config .= "define( \"WP_TESTS_TITLE\", \"EAN to PoS tests\" );\n";
		$config .= "define( \"WP_PHP_BINARY\", \"php\" );\n";
		$config .= "define( \"WPLANG\", \"\" );\n";
		$config .= "\$table_prefix = \"wptests_\";\n";
		file_put_contents($dest, $config);
	' "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_CORE_DIR" "$ROOT/tests/wp-tests-config.php"
}

install_db() {
	php -r '
		[$host, $name, $user, $pass] = array_slice($argv, 1);
		if (!preg_match("/^[A-Za-z0-9_]+$/", $name)) {
			fwrite(STDERR, "Invalid database name.\n");
			exit(1);
		}
		$parts = explode(":", $host, 2);
		$dsn = "mysql:host={$parts[0]}";
		if (isset($parts[1]) && $parts[1] !== "") {
			$dsn .= ";port={$parts[1]}";
		}
		$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}`");
	' "$DB_HOST" "$DB_NAME" "$DB_USER" "$DB_PASS"
}

install_core
install_woocommerce
install_config
install_db

echo "WordPress ${WP_VERSION} is ready in ${WP_CORE_DIR}"
