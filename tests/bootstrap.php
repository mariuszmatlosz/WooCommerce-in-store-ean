<?php
/**
 * Loads WordPress, WooCommerce and the plugin for integration tests.
 *
 * @package EanToPos
 */

$tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $tests_dir ) {
	$tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! is_readable( $tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test library is missing. Run composer install.\n";
	exit( 1 );
}

if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) || ! is_readable( getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) ) {
	echo "WordPress test config is missing. Run composer test:install.\n";
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		$woocommerce = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		if ( is_readable( $woocommerce ) ) {
			require $woocommerce;
		}

		require dirname( __DIR__ ) . '/ean-to-pos.php';
	}
);

tests_add_filter(
	'init',
	static function () {
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::create_tables();
		}
	},
	0
);

require $tests_dir . '/includes/bootstrap.php';

foreach ( array( 'class-documents.php', 'class-settings.php', 'class-register.php', 'class-admin.php' ) as $file ) {
	require_once dirname( __DIR__ ) . '/includes/' . $file;
}
