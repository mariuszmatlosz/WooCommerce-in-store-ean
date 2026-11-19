<?php
/**
 * Seed the browser-test store, employee and product.
 *
 * Each run restores the document counter and the test product stock.
 *
 * @package EanToPos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

update_option(
	'woocommerce_onboarding_profile',
	array(
		'completed' => true,
		'skipped'   => true,
	)
);
update_option( 'woocommerce_task_list_hidden', 'yes' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_address', '1 Test Street' );
update_option( 'woocommerce_store_city', 'Warsaw' );
update_option( 'woocommerce_default_country', 'PL' );
update_option( 'woocommerce_store_postcode', '00-001' );

wp_update_user(
	array(
		'ID'         => 1,
		'first_name' => 'Ada',
		'last_name'  => 'Lovelace',
		'user_email' => 'ada@example.com',
	)
);
update_user_meta( 1, 'billing_first_name', 'Ada' );
update_user_meta( 1, 'billing_last_name', 'Lovelace' );
update_user_meta( 1, 'billing_email', 'ada@example.com' );

delete_option( 'ean_to_pos_sequences' );
delete_option( 'ean_to_pos_sequences_lock' );

if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
	$product_id = wc_get_product_id_by_sku( 'EANTEST1' );
	$product    = $product_id ? wc_get_product( $product_id ) : null;
	if ( ! $product instanceof WC_Product ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Barcode product' );
		$product->set_sku( 'EANTEST1' );
		$product->set_regular_price( '12.50' );
		$product->set_status( 'publish' );
	}
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 5 );
	$product->set_stock_status( 'instock' );
	$product->save();
}
