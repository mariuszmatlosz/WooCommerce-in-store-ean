<?php
/**
 * Integration tests for product lookup through WooCommerce.
 *
 * @package EanToPos
 */

use EanToPos\Register;

/**
 * Exercises scan parsing against WooCommerce products.
 */
class Register_Test extends WP_UnitTestCase {

	/**
	 * WooCommerce tables are created by loading the plugin in the test bootstrap.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not installed in the test WordPress.' );
		}
	}

	/**
	 * Repeated codes for one SKU become one line with that quantity.
	 */
	public function test_repeated_sku_scans_count_as_quantity() {
		$product = new WC_Product_Simple();
		$product->set_name( 'Counted product' );
		$product->set_sku( 'COUNT1' );
		$product->set_regular_price( '10' );
		$product->set_status( 'publish' );
		$product->save();

		$items = Register::products( "COUNT1\nCOUNT1\nCOUNT1", 'sale' );

		$this->assertSame( 3, $items[ $product->get_id() ]['quantity'] );
	}

	/**
	 * A code with no product is rejected before a sale can be reviewed.
	 */
	public function test_unknown_code_is_rejected() {
		$this->expectException( DomainException::class );

		Register::products( 'DOES-NOT-EXIST', 'sale' );
	}
}
