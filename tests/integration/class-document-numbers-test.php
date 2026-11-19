<?php
/**
 * Integration tests for document numbers stored in WordPress options.
 *
 * @package EanToPos
 */

use EanToPos\Documents;

/**
 * Exercises the public numbering API against the test database.
 */
class Document_Numbers_Test extends WP_UnitTestCase {

	/**
	 * Starts each test from the plugin defaults and an empty counter.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'ean_to_pos_settings', array() );
		delete_option( 'ean_to_pos_sequences' );
	}

	/**
	 * A stored counter moves the next suggestion forward.
	 */
	public function test_receipt_counter_suggests_the_next_sequence() {
		update_option(
			'ean_to_pos_sequences',
			array(
				'receipt' => array( 'continuous' => 4 ),
			)
		);

		$this->assertSame( '5', Documents::next_number( 'receipt' ) );
	}

	/**
	 * A number typed into the form is incremented directly.
	 */
	public function test_explicit_number_increments_without_reading_the_counter() {
		$this->assertSame( '10', Documents::next_number( 'receipt', '9' ) );
	}

	/**
	 * The default invoice pattern includes the site's current month and year.
	 */
	public function test_invoice_suggestion_includes_the_current_period() {
		$now = current_datetime();

		$this->assertSame( 'S3/001/' . $now->format( 'm/Y' ), Documents::next_number( 'invoice' ) );
	}

	/**
	 * A bare sequence is padded into the configured invoice pattern.
	 */
	public function test_normalize_pads_a_bare_sequence() {
		$now = current_datetime();

		$this->assertSame( 'S3/007/' . $now->format( 'm/Y' ), Documents::normalize( 'invoice', '7' ) );
	}

	/**
	 * Text that is neither a sequence nor a full reference is rejected.
	 */
	public function test_normalize_rejects_a_number_that_does_not_match() {
		$this->expectException( DomainException::class );

		Documents::normalize( 'invoice', 'not-a-number' );
	}
}
