<?php
/**
 * Plugin Name: EAN to PoS
 * Description: Scan EANs or SKUs to record in-store WooCommerce sales and stock movements from WordPress Admin.
 * Version: 0.3.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Mariusz Matłosz (virgin71)
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * Text Domain: ean-to-pos
 * Domain Path: /languages
 *
 * @package EanToPos
 */

namespace EanToPos;

defined( 'ABSPATH' ) || exit;

define( 'EAN_TO_POS_VERSION', '0.3.1' );
define( 'EAN_TO_POS_FILE', __FILE__ );
define( 'EAN_TO_POS_URL', plugin_dir_url( __FILE__ ) );
define( 'EAN_TO_POS_PATH', plugin_dir_path( __FILE__ ) );

// 10up Toolkit writes this file while `npm run start` is serving the register assets.
if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) && is_readable( EAN_TO_POS_PATH . 'dist/fast-refresh.php' ) ) {
	require_once EAN_TO_POS_PATH . 'dist/fast-refresh.php';

	if ( function_exists( '\TenUpToolkit\set_dist_url_path' ) ) {
		\TenUpToolkit\set_dist_url_path( basename( EAN_TO_POS_PATH ), EAN_TO_POS_URL . 'dist/', EAN_TO_POS_PATH . 'dist/' );
	}
}

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'woocommerce_init',
	static function () {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Load bundled translations when running directly from the repository.
		load_plugin_textdomain( 'ean-to-pos', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		require_once __DIR__ . '/includes/class-register.php';
		require_once __DIR__ . '/includes/class-documents.php';
		require_once __DIR__ . '/includes/class-settings.php';
		require_once __DIR__ . '/includes/class-admin.php';

		add_action( 'admin_menu', array( Admin::class, 'menu' ) );
		add_action( 'admin_menu', array( Settings::class, 'menu' ) );
		add_action( 'admin_init', array( Settings::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( Admin::class, 'enqueue' ) );
		add_action( 'admin_post_ean_to_pos_create_draft', array( Admin::class, 'create_draft' ) );
		add_action( 'wp_ajax_ean_to_pos_next_number', array( Admin::class, 'next_number' ) );
		add_filter( 'option_page_capability_ean_to_pos_settings', array( Settings::class, 'capability' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( Admin::class, 'show_employee' ) );
	}
);
