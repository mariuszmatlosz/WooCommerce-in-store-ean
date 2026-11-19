<?php
/**
 * WordPress Admin interface for the register.
 *
 * @package EanToPos
 */

namespace EanToPos;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens for the in-store register.
 */
final class Admin {

	public const SLUG        = 'ean-to-pos';
	private const CAPABILITY = 'edit_shop_orders';
	/**
	 * Hook suffix of the register screen.
	 *
	 * @var string|false
	 */
	private static $screen;

	/**
	 * Add the register menu.
	 *
	 * @return void
	 */
	public static function menu() {
		// WooCommerce's Marketing menu uses position 58.
		self::$screen = add_menu_page( 'EAN to PoS', 'EAN to PoS', self::CAPABILITY, self::SLUG, array( self::class, 'render' ), 'dashicons-cart', '58.1' );
		add_submenu_page( self::SLUG, 'EAN to PoS', __( 'Register', 'ean-to-pos' ), self::CAPABILITY, self::SLUG );
	}

	/**
	 * Load the register script and style on its admin screen.
	 *
	 * @param mixed $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( self::$screen !== $hook ) {
			return;
		}

		$asset_path = EAN_TO_POS_PATH . 'dist/js/admin.asset.php';
		$asset      = is_readable( $asset_path ) ? require $asset_path : array(
			'dependencies' => array(),
			'version'      => EAN_TO_POS_VERSION,
		);

		wp_enqueue_script(
			'ean-to-pos-register',
			EAN_TO_POS_URL . 'dist/js/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style(
			'ean-to-pos-register',
			EAN_TO_POS_URL . 'dist/css/admin.css',
			array(),
			$asset['version']
		);
	}

	/**
	 * Build a register admin URL.
	 *
	 * @param mixed $operation Register operation.
	 * @return mixed
	 */
	private static function url( $operation = '' ) {
		$url = admin_url( 'admin.php?page=' . self::SLUG );
		return $operation ? add_query_arg( 'operation', $operation, $url ) : $url;
	}

	/**
	 * Render the register and settings tabs.
	 *
	 * @param mixed $settings Whether the settings tab is active.
	 * @return void
	 */
	public static function tabs( $settings = false ) {
		?>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'PoS navigation', 'ean-to-pos' ); ?>">
			<a class="nav-tab<?php echo $settings ? '' : ' nav-tab-active'; ?>" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Register', 'ean-to-pos' ); ?></a>
			<?php if ( current_user_can( Settings::CAPABILITY ) ) : ?>
				<a class="nav-tab<?php echo $settings ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( Settings::url() ); ?>"><?php esc_html_e( 'Settings', 'ean-to-pos' ); ?></a>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * Return the next or reset document number over Ajax.
	 *
	 * @return void
	 */
	public static function next_number() {
		check_ajax_referer( 'ean_to_pos_next_number' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( null, 403 );
		}

		$type    = isset( $_POST['document_type'] ) && is_string( $_POST['document_type'] ) ? sanitize_key( wp_unslash( $_POST['document_type'] ) ) : '';
		$current = isset( $_POST['document_number'] ) && is_string( $_POST['document_number'] ) ? sanitize_text_field( wp_unslash( $_POST['document_number'] ) ) : '';
		if ( '' === $type || ! isset( Documents::types()[ $type ] ) || strlen( $current ) > 100 ) {
			wp_send_json_error( null, 400 );
		}

		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$next = 'reset' === $mode ? Documents::starting_number( $type ) : Documents::next_number( $type, $current );
		if ( '' === $next ) {
			wp_send_json_error( array( 'message' => __( 'No higher number is available in this series.', 'ean-to-pos' ) ) );
		}

		wp_send_json_success( array( 'number' => $next ) );
	}

	/**
	 * Payment labels shown on the register.
	 *
	 * @return mixed
	 */
	public static function payments() {
		return array(
			'cash'     => __( 'Cash', 'ean-to-pos' ),
			'card'     => __( 'Card', 'ean-to-pos' ),
			'transfer' => __( 'Bank transfer', 'ean-to-pos' ),
			'other'    => __( 'Other', 'ean-to-pos' ),
		);
	}

	/**
	 * Show the PoS employee and document on an order.
	 *
	 * @param mixed $order WooCommerce order being viewed.
	 * @return void
	 */
	public static function show_employee( $order ) {
		$id = absint( $order->get_meta( '_ean_to_pos_employee_id' ) );
		if ( ! $id ) {
			return;
		}

		$employee      = get_user_by( 'id', $id );
		$document_type = $order->get_meta( '_ean_to_pos_document_type' );
		$fields        = array(
			__( 'PoS employee', 'ean-to-pos' )       => $employee ? $employee->display_name . ' (#' . $id . ')' : '#' . $id,
			__( 'Document type', 'ean-to-pos' )      => $document_type ? ( Documents::labels()[ $document_type ] ?? '' ) : '',
			__( 'Document number', 'ean-to-pos' )    => $order->get_meta( '_ean_to_pos_document_number' ),
			__( 'Shipment reference', 'ean-to-pos' ) => $order->get_meta( '_ean_to_pos_shipment_reference' ),
		);
		foreach ( $fields as $label => $value ) {
			if ( '' !== $value ) {
				echo '<p><strong>' . esc_html( $label ) . '</strong><br>' . esc_html( $value ) . '</p>';
			}
		}
	}

	/**
	 * Render the register screen.
	 *
	 * @return void
	 * @throws \RuntimeException When the reviewed operation cannot be stored.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You cannot access EAN to PoS.', 'ean-to-pos' ) );
		}

		// The operation in the URL only selects a form. Changes require the POST nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$operation = isset( $_GET['operation'] ) && is_string( $_GET['operation'] ) ? sanitize_key( wp_unslash( $_GET['operation'] ) ) : '';
		$post      = isset( $_SERVER['REQUEST_METHOD'] ) && 'post' === sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) );
		$address   = Register::store_address();
		$employee  = Register::employee();
		$stock     = current_user_can( 'edit_products' );
		if ( ! isset( self::operations()[ $operation ] ) || ( 'sale' !== $operation && ! $stock ) ) {
			$operation = $post ? 'sale' : '';
		}
		$data     = array(
			'operation'          => $operation,
			'scans'              => '',
			'payment'            => 'cash',
			'document_type'      => Settings::get()['default_document'],
			'document_number'    => '',
			'shipment_reference' => '',
		);
		$defaults = $data;
		$prefill  = true;
		$items    = array();
		$token    = '';
		$message  = '';
		$error    = '';
		$order    = null;
		$total    = 0;
		$negative = 0;
		$missing  = array();
		$received = false;

		if ( $post ) {
			check_admin_referer( 'ean_to_pos_register' );
			try {
				$confirmation = isset( $_POST['ean_to_pos_confirmation'] ) && is_string( $_POST['ean_to_pos_confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['ean_to_pos_confirmation'] ) ) : '';
				if ( '' !== $confirmation ) {
					$result                = self::confirm( $confirmation, $address, $stock, $data, $missing );
					$defaults['operation'] = $data['operation'];
					$data                  = $defaults;
					if ( $result instanceof \WC_Order ) {
						$order   = $result;
						$message = __( 'Sale recorded.', 'ean-to-pos' );
					} else {
						$received = 'receive' === $data['operation'];
						$message  = null === $result ? __( 'No stock was updated.', 'ean-to-pos' ) : __( 'Stock updated.', 'ean-to-pos' );
						$negative = $result;
					}
				} else {
					$prefill = false;
					foreach ( $data as $field => $default ) {
						if ( isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ) {
							$data[ $field ] = 'scans' === $field ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
						}
					}
					$items = self::validate( $data, $address, $stock, $missing );
					$total = 'sale' === $data['operation'] ? Register::total( $items ) : 0;
					if ( ! isset( $_POST['ean_to_pos_edit'] ) ) {
						$token = wp_generate_uuid4();
						$saved = set_transient(
							self::key( $token ),
							array(
								'data'        => $data,
								'fingerprint' => self::fingerprint( $items, $data, $address ),
							),
							15 * MINUTE_IN_SECONDS
						);
						if ( ! $saved ) {
							$token = '';
							throw new \RuntimeException();
						}
					}
				}
			} catch ( \DomainException $exception ) {
				$error = $exception->getMessage();
			} catch ( \Exception $exception ) {
				$error = __( 'The operation could not be confirmed. Check recent orders or stock before trying again.', 'ean-to-pos' );
			}
		}

		?>
		<div class="wrap">
			<h1>EAN to PoS</h1>
			<?php self::tabs(); ?>
			<?php if ( ! empty( $data['operation'] ) ) : ?>
				<p><a href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'All operations', 'ean-to-pos' ); ?></a></p>
			<?php endif; ?>
			<?php if ( $message ) : ?>
				<div class="notice notice-success"><p>
					<?php echo esc_html( $message ); ?>
					<?php if ( $order ) : ?>
						<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
							<?php
							/* translators: %s: WooCommerce order number. */
							printf( esc_html__( 'Open order #%s', 'ean-to-pos' ), esc_html( $order->get_order_number() ) );
							?>
						</a>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>
			<?php if ( $received && $missing ) : ?>
				<?php self::skipped_products( $missing, true ); ?>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<?php if ( $negative ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'The movement was saved. Some stock quantities are now below zero.', 'ean-to-pos' ); ?></p></div>
			<?php endif; ?>
			<?php if ( in_array( $data['operation'], array( '', 'sale' ), true ) && ( '' === $employee['first_name'] || '' === $employee['last_name'] || ! is_email( $employee['email'] ) ) ) : ?>
				<div class="notice notice-info"><p>
					<?php esc_html_e( 'Complete your name and email in your WooCommerce billing profile.', 'ean-to-pos' ); ?>
					<a href="<?php echo esc_url( get_edit_user_link( get_current_user_id() ) ); ?>"><?php esc_html_e( 'Complete your profile', 'ean-to-pos' ); ?></a>
				</p></div>
			<?php endif; ?>
			<?php if ( 'sale' === $data['operation'] && ! $address ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'Complete the billing address in WooCommerce or in PoS settings before recording sales.', 'ean-to-pos' ); ?>
					<?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ); ?>"><?php esc_html_e( 'Open WooCommerce settings', 'ean-to-pos' ); ?></a>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>

			<?php
			if ( ! $data['operation'] ) {
				self::dashboard( $stock );
			} elseif ( $token ) {
				self::preview( $data, $items, $token, $total, $missing );
			} else {
				$suggestions = 'sale' === $data['operation'] ? Documents::suggestions() : array();
				if ( $prefill && '' === $data['document_number'] ) {
					$data['document_number'] = $suggestions[ $data['document_type'] ] ?? '';
				}
				self::form( $data, $suggestions );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Build the transient key for a pending confirmation.
	 *
	 * @param mixed $token Confirmation token.
	 * @return mixed
	 */
	private static function key( $token ) {
		return 'ean_to_pos_' . get_current_user_id() . '_' . $token;
	}

	/**
	 * Hash the reviewed lines so confirmation can detect changes.
	 *
	 * @param mixed $items Reviewed product lines.
	 * @param mixed $data Submitted register fields.
	 * @param mixed $address Billing address.
	 * @return mixed
	 */
	private static function fingerprint( $items, $data, $address ) {
		$sale  = 'sale' === $data['operation'];
		$lines = array();
		foreach ( $items as $id => $item ) {
			$lines[ $id ] = array( $item['quantity'], $sale ? $item['product']->get_price() : $item['product']->get_stock_managed_by_id() );
		}
		if ( $sale ) {
			$lines[] = array( get_woocommerce_currency(), Register::total( $items ), $address, Settings::get()[ $data['document_type'] ] ?? array() );
		}
		return hash( 'sha256', wp_json_encode( $lines ) );
	}

	/**
	 * Confirm a previously reviewed operation.
	 *
	 * @param mixed $token Confirmation token.
	 * @param mixed $address Billing address.
	 * @param mixed $stock Whether the user can edit product stock.
	 * @param mixed $data Submitted register fields.
	 * @param array $missing Skipped codes.
	 * @return mixed
	 * @throws \DomainException When the confirmation is no longer valid.
	 */
	private static function confirm( $token, $address, $stock, &$data, &$missing ) {
		if ( ! wp_is_uuid( $token, 4 ) ) {
			throw new \DomainException( esc_html__( 'Invalid confirmation. Review the scans again.', 'ean-to-pos' ) );
		}
		$key  = self::key( $token );
		$lock = $key . '_lock';
		if ( ! get_transient( $key ) || ! add_option( $lock, 'claimed', '', false ) ) {
			throw new \DomainException( esc_html__( 'This confirmation has expired or was already submitted.', 'ean-to-pos' ) );
		}

		try {
			$pending = get_transient( $key );
			if ( ! $pending || ! delete_transient( $key ) ) {
				throw new \DomainException( esc_html__( 'This confirmation has expired or was already submitted.', 'ean-to-pos' ) );
			}

			$data  = $pending['data'];
			$items = self::validate( $data, $address, $stock, $missing );
			$sale  = 'sale' === $data['operation'];
			if ( ! hash_equals( $pending['fingerprint'], self::fingerprint( $items, $data, $address ) ) ) {
				throw new \DomainException( esc_html__( 'Products, prices or PoS settings changed. Review the sale again.', 'ean-to-pos' ) );
			}

			if ( $sale ) {
				return Documents::record_sale( $items, $data, $address );
			}
			return $items ? Register::move_stock( $items, $data['operation'] ) : null;
		} finally {
			delete_option( $lock );
		}
	}

	/**
	 * Validate a submitted register form.
	 *
	 * @param mixed $data Submitted register fields.
	 * @param mixed $address Billing address.
	 * @param mixed $stock Whether the user can edit product stock.
	 * @param array $missing Skipped codes.
	 * @return mixed
	 * @throws \DomainException When the submitted operation cannot be reviewed.
	 */
	private static function validate( &$data, $address, $stock, &$missing ) {
		if ( ! in_array( $data['operation'], array( 'sale', 'receive', 'remove' ), true ) || ( 'sale' !== $data['operation'] && ! $stock ) ) {
			throw new \DomainException( esc_html__( 'You cannot perform that operation.', 'ean-to-pos' ) );
		}
		if ( 'sale' === $data['operation'] ) {
			if ( empty( $address ) ) {
				throw new \DomainException( esc_html__( 'Complete the store address before recording a sale.', 'ean-to-pos' ) );
			}
			if ( ! isset( self::payments()[ $data['payment'] ], Documents::types()[ $data['document_type'] ] ) ) {
				throw new \DomainException( esc_html__( 'Choose a valid payment method and document type.', 'ean-to-pos' ) );
			}
			$data['document_number'] = Documents::normalize( $data['document_type'], $data['document_number'] );
			if ( strlen( $data['shipment_reference'] ) > 200 ) {
				throw new \DomainException( esc_html__( 'The shipment reference must fit within 200 bytes.', 'ean-to-pos' ) );
			}
		}
		if ( strlen( $data['scans'] ) > 30000 ) {
			throw new \DomainException( esc_html__( 'The scan list is too long.', 'ean-to-pos' ) );
		}
		return Register::products( $data['scans'], $data['operation'], $missing );
	}

	/**
	 * Render a select field.
	 *
	 * @param mixed $field Field name.
	 * @param mixed $label Visible field label.
	 * @param mixed $options Options keyed by value.
	 * @param mixed $value Selected value.
	 * @return void
	 */
	private static function select( $field, $label, $options, $value ) {
		?>
		<p><label for="ean-to-pos-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label><br>
			<select id="ean-to-pos-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>">
				<?php foreach ( $options as $key => $text ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $value ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Labels for the register operations.
	 *
	 * @return mixed
	 */
	private static function operations() {
		return array(
			'sale'    => __( 'Add sale', 'ean-to-pos' ),
			'receive' => __( 'Return / Receive stock', 'ean-to-pos' ),
			'remove'  => __( 'Issue stock', 'ean-to-pos' ),
		);
	}

	/**
	 * Render the operation chooser.
	 *
	 * @param mixed $stock Whether the user can edit product stock.
	 * @return void
	 */
	private static function dashboard( $stock ) {
		?>
		<div class="ean-to-pos-actions">
			<?php foreach ( self::operations() as $operation => $label ) : ?>
				<?php if ( 'sale' === $operation || $stock ) : ?>
					<a class="button button-large<?php echo 'sale' === $operation ? ' button-primary' : ''; ?>" href="<?php echo esc_url( self::url( $operation ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render the scan form.
	 *
	 * @param mixed $data Submitted register fields.
	 * @param mixed $suggestions Suggested numbers keyed by document type.
	 * @return void
	 */
	private static function form( $data, $suggestions ) {
		$sale = 'sale' === $data['operation'];
		?>
		<h2><?php echo esc_html( self::operations()[ $data['operation'] ] ?? '' ); ?></h2>
		<form method="post" action="<?php echo esc_url( self::url( $data['operation'] ) ); ?>">
			<?php wp_nonce_field( 'ean_to_pos_register' ); ?>
			<input type="hidden" name="operation" value="<?php echo esc_attr( $data['operation'] ); ?>">
			<p>
				<label for="ean-to-pos-scans"><strong><?php esc_html_e( 'Scan EANs or SKUs', 'ean-to-pos' ); ?></strong></label><br>
				<textarea id="ean-to-pos-scans" class="ean-to-pos-scans code" name="scans" rows="<?php echo $sale ? 7 : 15; ?>" maxlength="30000" aria-describedby="ean-to-pos-help" required autofocus><?php echo esc_textarea( $data['scans'] ); ?></textarea>
				<br><span class="description" id="ean-to-pos-help"><?php esc_html_e( 'Scan one code per line. Each repeat adds one unit.', 'ean-to-pos' ); ?></span>
			</p>
			<?php if ( $sale ) : ?>
				<fieldset>
					<legend><strong><?php esc_html_e( 'Sale details', 'ean-to-pos' ); ?></strong></legend>
					<fieldset class="ean-to-pos-payments">
						<legend><?php esc_html_e( 'Payment received', 'ean-to-pos' ); ?></legend>
						<?php foreach ( self::payments() as $method => $label ) : ?>
							<label><input name="payment" type="radio" value="<?php echo esc_attr( $method ); ?>" <?php checked( $method, $data['payment'] ); ?> required> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</fieldset>
					<?php
					self::select( 'document_type', __( 'Document type', 'ean-to-pos' ), Documents::types(), $data['document_type'] );
					?>
					<p id="ean-to-pos-document-field" class="ean-to-pos-document-field"><label for="ean-to-pos-document"><?php esc_html_e( 'Document number', 'ean-to-pos' ); ?></label><br>
						<input id="ean-to-pos-document" name="document_number" type="text" maxlength="80" value="<?php echo esc_attr( $data['document_number'] ); ?>" data-suggestions="<?php echo esc_attr( wp_json_encode( $suggestions ) ); ?>">
						<button id="ean-to-pos-next-number" type="button" class="button ean-to-pos-number-action" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ean_to_pos_next_number' ) ); ?>" data-error="<?php esc_attr_e( 'Could not get the next number. Try again.', 'ean-to-pos' ); ?>" hidden><?php esc_html_e( 'Use next number', 'ean-to-pos' ); ?></button>
						<button id="ean-to-pos-reset-number" type="button" class="button ean-to-pos-number-action" data-error="<?php esc_attr_e( 'Could not reset the document number. Try again.', 'ean-to-pos' ); ?>" hidden><?php esc_html_e( 'Reset', 'ean-to-pos' ); ?></button>
						<span id="ean-to-pos-number-status" role="status"></span>
					</p>
					<p><label for="ean-to-pos-shipment"><?php esc_html_e( 'Shipment reference (optional)', 'ean-to-pos' ); ?></label><br>
						<input id="ean-to-pos-shipment" name="shipment_reference" type="text" maxlength="200" value="<?php echo esc_attr( $data['shipment_reference'] ); ?>">
					</p>
				</fieldset>
			<?php endif; ?>
			<?php submit_button( __( 'Review scans', 'ean-to-pos' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render the review step.
	 *
	 * @param mixed $data Submitted register fields.
	 * @param mixed $items Reviewed product lines.
	 * @param mixed $token Confirmation token.
	 * @param mixed $total Submitted value.
	 * @param array $missing Skipped codes.
	 * @return void
	 */
	private static function preview( $data, $items, $token, $total, $missing ) {
		$sale       = 'sale' === $data['operation'];
		$projection = $sale ? array() : Register::projected_stock( $items, $data['operation'] );
		?>
		<h2><?php echo esc_html( self::operations()[ $data['operation'] ] ); ?></h2>
		<?php if ( $sale ) : ?>
			<p><?php echo esc_html( self::payments()[ $data['payment'] ] ); ?></p>
			<?php if ( '' !== $data['document_number'] ) : ?>
				<p><?php echo esc_html( Documents::labels()[ $data['document_type'] ] . ' ' . $data['document_number'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $data['shipment_reference'] ) : ?>
				<p><?php echo esc_html( $data['shipment_reference'] ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
		if ( array_filter(
			$projection,
			static function ( $stock ) {
				return $stock['after'] < 0; }
		) ) :
			?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'Insufficient stock. You can confirm this movement with the negative quantities shown below.', 'ean-to-pos' ); ?></p></div>
		<?php endif; ?>
		<?php if ( $missing ) : ?>
			<?php self::skipped_products( $missing, false ); ?>
		<?php endif; ?>
		<?php if ( $items ) : ?>
		<table class="widefat striped">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Product', 'ean-to-pos' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Quantity', 'ean-to-pos' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Current stock', 'ean-to-pos' ); ?></th>
				<?php if ( ! $sale ) : ?>
					<th scope="col"><?php esc_html_e( 'Stock after movement', 'ean-to-pos' ); ?></th>
				<?php endif; ?>
				<th scope="col"><?php esc_html_e( 'Price per unit', 'ean-to-pos' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $items as $item ) : ?>
				<?php $stock = $projection[ $item['product']->get_stock_managed_by_id() ] ?? null; ?>
				<tr>
					<th scope="row"><?php echo esc_html( $item['product']->get_name() ); ?></th>
					<td><?php echo esc_html( (string) $item['quantity'] ); ?></td>
					<td><?php echo esc_html( (string) ( $stock['current'] ?? $item['product']->get_stock_quantity() ?? '—' ) ); ?></td>
					<?php if ( $stock ) : ?>
						<td class="<?php echo $stock['after'] < 0 ? 'ean-to-pos-negative' : ''; ?>"><?php echo esc_html( (string) $stock['after'] ); ?></td>
					<?php endif; ?>
					<td>
						<?php if ( '' === $item['product']->get_price() ) : ?>
							<?php esc_html_e( 'No price', 'ean-to-pos' ); ?>
						<?php else : ?>
							<?php echo wp_kses_post( wc_price( Register::unit_price( $item['product'] ) ) ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
		<?php if ( $sale ) : ?>
			<p><strong><?php esc_html_e( 'Total', 'ean-to-pos' ); ?> <?php echo wp_kses_post( wc_price( $total ) ); ?></strong></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( self::url( $data['operation'] ) ); ?>">
			<?php wp_nonce_field( 'ean_to_pos_register' ); ?>
			<input type="hidden" name="ean_to_pos_confirmation" value="<?php echo esc_attr( $token ); ?>">
			<?php submit_button( $sale ? __( 'Record paid sale', 'ean-to-pos' ) : __( 'Confirm stock movement', 'ean-to-pos' ) ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( self::url( $data['operation'] ) ); ?>">
			<?php wp_nonce_field( 'ean_to_pos_register' ); ?>
			<input type="hidden" name="ean_to_pos_edit" value="1">
			<?php foreach ( $data as $field => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php endforeach; ?>
			<?php submit_button( __( 'Edit scans', 'ean-to-pos' ), 'secondary' ); ?>
		</form>
		<?php
	}
	/**
	 * Show skipped codes and, after confirmation, draft creation actions.
	 *
	 * @param array $missing Skipped code quantities.
	 * @param bool  $confirmed Whether the receipt has been confirmed.
	 */
	private static function skipped_products( $missing, $confirmed ) {
		?>
		<div class="notice notice-warning inline">
			<p><?php echo $confirmed ? esc_html__( 'These codes were skipped. Create or open a product draft to add them to the catalog.', 'ean-to-pos' ) : esc_html__( 'These codes have no active product and will be skipped.', 'ean-to-pos' ); ?></p>
			<ul class="ul-disc">
				<?php foreach ( $missing as $code => $quantity ) : ?>
					<li>
						<code><?php echo esc_html( (string) $code ); ?></code> × <?php echo esc_html( (string) $quantity ); ?>
						<?php if ( $confirmed && current_user_can( 'edit_products' ) ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank">
								<?php wp_nonce_field( 'ean_to_pos_create_draft_' . $code, '_wpnonce', false ); ?>
								<input type="hidden" name="action" value="ean_to_pos_create_draft">
								<input type="hidden" name="code" value="<?php echo esc_attr( (string) $code ); ?>">
								<button class="button-link" type="submit"><?php esc_html_e( 'Create or open product draft', 'ean-to-pos' ); ?></button>
							</form>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Create or open a product draft from a skipped code.
	 */
	public static function create_draft() {
		// The nonce action incorporates the sanitized code.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$code = isset( $_POST['code'] ) && is_string( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		check_admin_referer( 'ean_to_pos_create_draft_' . $code );
		if ( ! current_user_can( self::CAPABILITY ) || ! current_user_can( 'edit_products' ) || '' === $code || strlen( $code ) > 100 ) {
			wp_die( esc_html__( 'You cannot create this product draft.', 'ean-to-pos' ) );
		}

		$lock = 'ean_to_pos_draft_' . hash( 'sha256', strtolower( $code ) );
		if ( ! add_option( $lock, 'claimed', '', false ) ) {
			wp_die( esc_html__( 'This product draft is already being created. Try again in a moment.', 'ean-to-pos' ) );
		}

		try {
			$id = Register::product_id( $code );
			if ( ! $id ) {
				$product = new \WC_Product_Simple();
				$product->set_name( $code );
				$product->set_status( 'draft' );
				if ( preg_match( '/\A[0-9]{8,14}\z/', $code ) && method_exists( $product, 'set_global_unique_id' ) ) {
					$product->set_global_unique_id( $code );
				} else {
					$product->set_sku( $code );
				}
				$id = $product->save();
			}
		} catch ( \Exception $error ) {
			$id = 0;
		} finally {
			delete_option( $lock );
		}

		$url = $id && current_user_can( 'edit_product', $id ) ? get_edit_post_link( $id, 'raw' ) : '';
		if ( ! $url ) {
			wp_die( esc_html__( 'Could not open the product draft.', 'ean-to-pos' ) );
		}
		wp_safe_redirect( $url );
		exit;
	}
}
