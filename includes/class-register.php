<?php
/**
 * Product lookup, billing and register operations.
 *
 * @package EanToPos
 */

namespace EanToPos;

defined( 'ABSPATH' ) || exit;

/**
 * Product lookup, billing and stock operations.
 */
final class Register {

	public const MAX_SCANS = 500;

	/**
	 * Resolve scanned codes to priced products and quantities.
	 *
	 * @param mixed $scans Raw scan list.
	 * @param mixed $operation Register operation.
	 * @param array $missing Unrecognized codes and their quantities for receipts.
	 * @return mixed
	 * @throws \DomainException When a code cannot be sold or moved.
	 */
	public static function products( $scans, $operation, &$missing = array() ) {
		$missing = array();
		$codes   = preg_split( '/[\s,]+/', $scans, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $codes || count( $codes ) > self::MAX_SCANS ) {
			throw new \DomainException( esc_html__( 'Scan between 1 and 500 codes.', 'ean-to-pos' ) );
		}

		$items = array();
		foreach ( array_count_values( $codes ) as $code => $quantity ) {
			$code    = (string) $code;
			$id      = self::product_id( $code );
			$product = $id ? wc_get_product( $id ) : false;

			$active = $product && 'publish' === $product->get_status();
			if ( $active && $product->is_type( 'variation' ) ) {
				$active = 'publish' === get_post_status( $product->get_parent_id() );
			}
			if ( 'receive' === $operation && ! $active ) {
				$missing[ $code ] = $quantity;
				continue;
			}
			if ( ! $active || ! $product->is_type( array( 'simple', 'variation' ) ) ) {
				throw new \DomainException(
					/* translators: %s: scanned code. */
					sprintf( esc_html__( 'No active simple product or variation matches %s.', 'ean-to-pos' ), esc_html( $code ) )
				);
			}

			if ( ! isset( $items[ $id ] ) ) {
				$items[ $id ] = array(
					'product'  => $product,
					'quantity' => 0,
				);
			}
			$items[ $id ]['quantity'] += $quantity;
		}

		$stock = array();
		foreach ( $items as $item ) {
			$product = $item['product'];
			if ( 'sale' === $operation ) {
				if ( '' === $product->get_price() || ! $product->is_in_stock() ) {
					throw new \DomainException(
						/* translators: %s: product name. */
						sprintf( esc_html__( '%s is out of stock or has no price.', 'ean-to-pos' ), esc_html( $product->get_name() ) )
					);
				}
			} elseif ( ! current_user_can( 'edit_product', $product->get_id() ) || ! current_user_can( 'edit_product', $product->get_stock_managed_by_id() ) || ! $product->managing_stock() ) {
				throw new \DomainException(
					/* translators: %s: product name. */
					sprintf( esc_html__( 'Quantity tracking and permission to edit stock are required for %s.', 'ean-to-pos' ), esc_html( $product->get_name() ) )
				);
			}

			if ( 'sale' === $operation && $product->managing_stock() && ! $product->backorders_allowed() ) {
				$owner           = $product->get_stock_managed_by_id();
				$stock[ $owner ] = ( $stock[ $owner ] ?? 0 ) + $item['quantity'];
				if ( $stock[ $owner ] > wc_get_product( $owner )->get_stock_quantity() ) {
					throw new \DomainException(
						/* translators: %s: product name. */
						sprintf( esc_html__( 'Insufficient stock for %s.', 'ean-to-pos' ), esc_html( $product->get_name() ) )
					);
				}
			}
		}
		return $items;
	}

	/**
	 * Resolve a scanned code, including existing product drafts.
	 *
	 * @param string $code Scanned code.
	 * @return int
	 */
	public static function product_id( $code ) {
		$id = function_exists( 'wc_get_product_id_by_global_unique_id' ) ? wc_get_product_id_by_global_unique_id( $code ) : 0;
		return $id ? $id : wc_get_product_id_by_sku( $code );
	}

	/**
	 * Return the billing address used for PoS orders.
	 *
	 * @return mixed
	 */
	public static function store_address() {
		$settings = Settings::get();
		$store    = $settings['custom_address'] ? $settings['address'] : self::woocommerce_address();
		return self::address_complete( $store ) ? $store : array();
	}

	/**
	 * Return the WooCommerce store address.
	 *
	 * @return mixed
	 */
	public static function woocommerce_address() {
		$countries = WC()->countries;
		return array(
			'address_1' => $countries->get_base_address(),
			'address_2' => $countries->get_base_address_2(),
			'city'      => $countries->get_base_city(),
			'postcode'  => $countries->get_base_postcode(),
			'country'   => $countries->get_base_country(),
			'state'     => $countries->get_base_state(),
		);
	}

	/**
	 * Return the current employee name and email.
	 *
	 * @return mixed
	 */
	public static function employee() {
		$user     = wp_get_current_user();
		$customer = new \WC_Customer( $user->ID );
		$identity = array();

		foreach ( array( 'first_name', 'last_name' ) as $name ) {
			$billing           = trim( $customer->{ 'get_billing_' . $name }( 'edit' ) );
			$shipping          = trim( $customer->{ 'get_shipping_' . $name }( 'edit' ) );
			$identity[ $name ] = '' !== $billing ? $billing : ( '' !== $shipping ? $shipping : trim( $user->$name ) );
		}

		// WooCommerce has a billing email field. Some extensions also add a shipping email.
		$identity['email'] = '';
		foreach ( array( $customer->get_billing_email( 'edit' ), get_user_meta( $user->ID, 'shipping_email', true ), $user->user_email ) as $email ) {
			if ( is_string( $email ) && is_email( trim( $email ) ) ) {
				$identity['email'] = trim( $email );
				break;
			}
		}
		return $identity;
	}

	/**
	 * Whether an address satisfies the country requirements.
	 *
	 * @param mixed $address Billing address.
	 * @return mixed
	 */
	public static function address_complete( $address ) {
		if ( empty( $address['country'] ) || ! isset( WC()->countries->get_countries()[ $address['country'] ] ) ) {
			return false;
		}

		$fields = WC()->countries->get_address_fields( $address['country'], '' );
		foreach ( array( 'address_1', 'city', 'postcode', 'state' ) as $field ) {
			if ( ! empty( $fields[ $field ]['required'] ) && '' === trim( $address[ $field ] ?? '' ) ) {
				return false;
			}
		}
		return '' !== trim( $address['address_1'] );
	}

	/**
	 * Create a completed order for a reviewed sale.
	 *
	 * @param mixed $items Reviewed product lines.
	 * @param mixed $data Submitted register fields.
	 * @param mixed $address Billing address.
	 * @return mixed
	 * @throws \RuntimeException When the completed order cannot be saved.
	 * @throws \Exception When saving the order fails.
	 */
	public static function order( $items, $data, $address ) {
		$order = new \WC_Order();
		$order->set_status( 'pending' );
		$order->set_created_via( 'ean-to-pos' );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_prices_include_tax( wc_prices_include_tax() );
		$order->set_customer_id( get_current_user_id() );
		$order->set_address( array_merge( $address, self::employee() ), 'billing' );
		$order->set_payment_method( 'ean_to_pos_' . $data['payment'] );
		$order->set_payment_method_title( Admin::payments()[ $data['payment'] ] );
		$order->update_meta_data( '_ean_to_pos_employee_id', get_current_user_id() );
		foreach ( array( 'document_type', 'document_number', 'shipment_reference' ) as $field ) {
			if ( '' !== $data[ $field ] ) {
				$order->update_meta_data( '_ean_to_pos_' . $field, $data[ $field ] );
			}
		}

		try {
			// Keep lines in memory until the complete order is ready to save.
			foreach ( $items as $entry ) {
				$order->add_item( self::line( $entry['product'], $entry['quantity'] ) );
			}
			$order->calculate_taxes( self::tax_location() );
			$order->calculate_totals( false );

			wc_reserve_stock_for_order( $order );
			$order->set_date_paid( time() );
			if ( ! $order->update_status( 'completed' ) ) {
				throw new \RuntimeException();
			}
		} catch ( \Exception $error ) {
			if ( $order->get_id() ) {
				wc_release_stock_for_order( $order );
				$persisted = wc_get_order( $order->get_id() );
				if ( $persisted && $persisted->has_status( 'pending' ) ) {
					$persisted->delete( true );
				} elseif ( $persisted && $persisted->has_status( 'completed' ) ) {
					return $persisted;
				}
			}
			throw $error;
		}
		return $order;
	}

	/**
	 * Store location used to calculate tax.
	 *
	 * @return mixed
	 */
	private static function tax_location() {
		$location             = wc_get_base_location();
		$location['city']     = WC()->countries->get_base_city();
		$location['postcode'] = WC()->countries->get_base_postcode();
		return $location;
	}

	/**
	 * Build an order line from a product and quantity.
	 *
	 * @param mixed $product WooCommerce product.
	 * @param mixed $quantity Quantity.
	 * @return mixed
	 */
	private static function line( $product, $quantity ) {
		$line = new \WC_Order_Item_Product();
		$line->set_product( $product );
		$line->set_quantity( $quantity );
		$amount = (float) $product->get_price() * $quantity;
		if ( $product->is_taxable() && wc_prices_include_tax() ) {
			$rates   = \WC_Tax::get_base_tax_rates( $product->get_tax_class() );
			$amount -= array_sum( \WC_Tax::calc_tax( $amount, $rates, true ) );
		}
		$line->set_subtotal( $amount );
		$line->set_total( $amount );
		return $line;
	}

	/**
	 * Return the tax-aware price of one unit.
	 *
	 * @param mixed $product WooCommerce product.
	 * @return mixed
	 */
	public static function unit_price( $product ) {
		return self::total(
			array(
				array(
					'product'  => $product,
					'quantity' => 1,
				),
			)
		);
	}

	/**
	 * Return the tax-aware total for the reviewed lines.
	 *
	 * @param mixed $items Reviewed product lines.
	 * @return mixed
	 */
	public static function total( $items ) {
		$net         = 0;
		$taxes       = array();
		$location    = self::tax_location();
		$round_lines = 'yes' !== get_option( 'woocommerce_tax_round_at_subtotal' );

		foreach ( $items as $entry ) {
			$line = self::line( $entry['product'], $entry['quantity'] );
			$net += (float) $line->get_total();
			$line->calculate_taxes( $location );
			foreach ( $line->get_taxes()['total'] as $rate => $amount ) {
				$taxes[ $rate ] = ( $taxes[ $rate ] ?? 0 ) + ( $round_lines ? wc_round_tax_total( $amount ) : (float) $amount );
			}
		}

		return round( $net + wc_round_tax_total( array_sum( $taxes ) ), wc_get_price_decimals() );
	}

	/**
	 * Sum quantities by the product that owns the stock.
	 *
	 * @param mixed $items Reviewed product lines.
	 * @return mixed
	 */
	private static function stock_quantities( $items ) {
		$totals = array();
		foreach ( $items as $item ) {
			$id            = $item['product']->get_stock_managed_by_id();
			$totals[ $id ] = ( $totals[ $id ] ?? 0 ) + $item['quantity'];
		}

		return $totals;
	}

	/**
	 * Calculate stock before and after a movement.
	 *
	 * @param mixed $items Reviewed product lines.
	 * @param mixed $operation Register operation.
	 * @return mixed
	 */
	public static function projected_stock( $items, $operation ) {
		$projection = array();
		foreach ( self::stock_quantities( $items ) as $id => $quantity ) {
			$current           = wc_get_product( $id )->get_stock_quantity();
			$projection[ $id ] = array(
				'current' => $current,
				'after'   => $current + ( 'receive' === $operation ? $quantity : -$quantity ),
			);
		}
		return $projection;
	}

	/**
	 * Apply a stock movement and count negative results.
	 *
	 * @param mixed $items Reviewed product lines.
	 * @param mixed $operation Register operation.
	 * @return mixed
	 * @throws \RuntimeException When WooCommerce rejects a stock update.
	 * @throws \DomainException When a stock update stops partway through.
	 */
	public static function move_stock( $items, $operation ) {
		$updated  = 0;
		$negative = 0;
		foreach ( self::stock_quantities( $items ) as $id => $quantity ) {
			try {
				$result = wc_update_product_stock( $id, $quantity, 'receive' === $operation ? 'increase' : 'decrease' );
				if ( ! is_numeric( $result ) ) {
					throw new \RuntimeException();
				}
				++$updated;
				if ( $result < 0 ) {
					++$negative;
				}
			} catch ( \Exception $error ) {
				throw new \DomainException(
					/* translators: %d: number of stock records updated before an error. */
					sprintf( esc_html__( 'Stock update interrupted. %d stock records were confirmed. Check current quantities before trying again.', 'ean-to-pos' ), absint( $updated ) )
				);
			}
		}
		return $negative;
	}
}
