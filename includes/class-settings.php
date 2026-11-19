<?php
/**
 * Address and document preferences for the register.
 *
 * @package EanToPos
 */

namespace EanToPos;

defined( 'ABSPATH' ) || exit;

/**
 * Address and document preferences for the register.
 */
final class Settings {

	public const CAPABILITY = 'manage_woocommerce';
	public const SLUG       = 'ean-to-pos-settings';
	private const GROUP     = 'ean_to_pos_settings';
	private const OPTION    = 'ean_to_pos_settings';

	/**
	 * Return the saved settings merged with the defaults.
	 *
	 * @return mixed
	 */
	public static function get() {
		$defaults = array(
			'custom_address'   => false,
			'address'          => array(),
			'invoices_enabled' => true,
			'default_document' => 'receipt',
			'receipt'          => array(
				'pattern' => '{number}',
				'digits'  => 1,
				'reset'   => 'continuous',
				'start'   => 1,
			),
			'invoice'          => array(
				'pattern' => 'S3/{number}/{month}/{year}',
				'digits'  => 3,
				'reset'   => 'monthly',
				'start'   => 1,
			),
		);
		return array_replace_recursive( $defaults, get_option( self::OPTION, array() ) );
	}

	/**
	 * Register the settings option.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
	}

	/**
	 * Capability required to save PoS settings.
	 *
	 * @return mixed
	 */
	public static function capability() {
		return self::CAPABILITY;
	}

	/**
	 * Add the settings submenu.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( Admin::SLUG, __( 'EAN to PoS Settings', 'ean-to-pos' ), __( 'Settings', 'ean-to-pos' ), self::CAPABILITY, self::SLUG, array( self::class, 'render' ) );
	}

	/**
	 * Build the settings admin URL.
	 *
	 * @return mixed
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Labels for the custom billing address fields.
	 *
	 * @return mixed
	 */
	private static function address_fields() {
		return array(
			'address_1' => __( 'Street address', 'ean-to-pos' ),
			'address_2' => __( 'Address line 2', 'ean-to-pos' ),
			'city'      => __( 'City', 'ean-to-pos' ),
			'postcode'  => __( 'Postcode', 'ean-to-pos' ),
		);
	}

	/**
	 * Labels for the sequence reset cycles.
	 *
	 * @return mixed
	 */
	private static function resets() {
		return array(
			'continuous' => __( 'Never', 'ean-to-pos' ),
			'monthly'    => __( 'Every month', 'ean-to-pos' ),
			'yearly'     => __( 'Every year', 'ean-to-pos' ),
		);
	}

	/**
	 * Validate and normalize a submitted settings form.
	 *
	 * @param mixed $input Submitted settings.
	 * @return mixed
	 */
	public static function sanitize( $input ) {
		$previous = self::get();
		if ( ! is_array( $input ) ) {
			return $previous;
		}

		$settings                     = $previous;
		$settings['custom_address']   = ! empty( $input['custom_address'] );
		$settings['invoices_enabled'] = ! empty( $input['invoices_enabled'] );
		$settings['default_document'] = $settings['invoices_enabled'] && 'invoice' === ( $input['default_document'] ?? '' ) ? 'invoice' : 'receipt';

		// Accept the normalized form as well as submitted fields on the first option save.
		if ( isset( $input['country_region'] ) ) {
			$country_region = sanitize_text_field( $input['country_region'] );
		} else {
			$country_region = ( $input['address']['country'] ?? '' ) . ':' . ( $input['address']['state'] ?? '' );
		}
		$parts   = explode( ':', $country_region, 2 );
		$address = array(
			'country' => $parts[0],
			'state'   => $parts[1] ?? '',
		);
		foreach ( self::address_fields() as $key => $label ) {
			$address[ $key ] = sanitize_text_field( $input['address'][ $key ] ?? '' );
		}
		if ( $settings['custom_address'] && ! Register::address_complete( $address ) ) {
			add_settings_error( self::OPTION, 'ean_to_pos_address', __( 'Complete the custom billing address before enabling it.', 'ean-to-pos' ) );
			return $previous;
		}
		$settings['address'] = $address;

		foreach ( array( 'receipt', 'invoice' ) as $type ) {
			$format  = $input[ $type ] ?? array();
			$pattern = sanitize_text_field( $format['pattern'] ?? '' );
			$reset   = sanitize_key( $format['reset'] ?? '' );
			if ( ! isset( self::resets()[ $reset ] ) || ! Documents::valid_pattern( $pattern, $reset ) ) {
				add_settings_error( self::OPTION, 'ean_to_pos_format', __( 'Use {number} once and at most one placeholder for each date component. Use separators between variable-width placeholders. Include a year for yearly numbering and both month and year for monthly numbering.', 'ean-to-pos' ) );
				return $previous;
			}
			$settings[ $type ] = array(
				'pattern' => $pattern,
				'digits'  => min( 9, max( 1, absint( $format['digits'] ?? 1 ) ) ),
				'reset'   => $reset,
				'start'   => min( Documents::MAX_SEQUENCE, max( 1, absint( $format['start'] ?? 1 ) ) ),
			);
		}
		return $settings;
	}

	/**
	 * Render the settings screen.
	 *
	 * @return mixed
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You cannot manage EAN to PoS settings.', 'ean-to-pos' ) );
		}

		$settings = self::get();
		$address  = $settings['address'] ? $settings['address'] : Register::woocommerce_address();
		?>
		<div class="wrap">
			<h1>EAN to PoS</h1>
			<?php Admin::tabs( true ); ?>
			<?php settings_errors( self::OPTION ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( self::GROUP ); ?>
				<h2><?php esc_html_e( 'PoS billing address', 'ean-to-pos' ); ?></h2>
				<p><label>
					<input name="ean_to_pos_settings[custom_address]" type="checkbox" value="1" <?php checked( $settings['custom_address'] ); ?>>
					<?php esc_html_e( 'Use this address on PoS orders instead of the WooCommerce store address', 'ean-to-pos' ); ?>
				</label></p>
				<?php foreach ( self::address_fields() as $key => $label ) : ?>
					<p><label for="ean-to-pos-address-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label><br>
						<input class="regular-text" id="ean-to-pos-address-<?php echo esc_attr( $key ); ?>" name="ean_to_pos_settings[address][<?php echo esc_attr( $key ); ?>]" type="text" value="<?php echo esc_attr( $address[ $key ] ); ?>">
					</p>
				<?php endforeach; ?>
				<p><label for="ean-to-pos-country-region"><?php esc_html_e( 'Country / Region', 'ean-to-pos' ); ?></label><br>
					<select id="ean-to-pos-country-region" name="ean_to_pos_settings[country_region]">
						<?php
						$state = $address['state'] ? $address['state'] : '*';
						WC()->countries->country_dropdown_options( $address['country'], $state, true );
						?>
					</select>
				</p>
				<h2><?php esc_html_e( 'Document numbering', 'ean-to-pos' ); ?></h2>
				<p><label>
					<input name="ean_to_pos_settings[invoices_enabled]" type="checkbox" value="1" <?php checked( $settings['invoices_enabled'] ); ?>>
					<?php esc_html_e( 'Show invoices in the register', 'ean-to-pos' ); ?>
				</label></p>
				<p><label for="ean-to-pos-default-document"><?php esc_html_e( 'Default document', 'ean-to-pos' ); ?></label><br>
					<select id="ean-to-pos-default-document" name="ean_to_pos_settings[default_document]">
						<option value="receipt" <?php selected( 'receipt', $settings['default_document'] ); ?>><?php esc_html_e( 'Receipt', 'ean-to-pos' ); ?></option>
						<option value="invoice" <?php selected( 'invoice', $settings['default_document'] ); ?>><?php esc_html_e( 'Invoice', 'ean-to-pos' ); ?></option>
					</select>
				</p>
				<details open>
					<summary><?php esc_html_e( 'How to write a format', 'ean-to-pos' ); ?></summary>
					<ul class="ul-disc">
						<li><code>{number}</code> <?php esc_html_e( 'goes in the format once. Set the minimum digits to 3 to display 7 as 007.', 'ean-to-pos' ); ?></li>
						<li><code>{m}</code>, <code>{month}</code> <?php esc_html_e( 'show the month as 01–12.', 'ean-to-pos' ); ?> <code>{n}</code> <?php esc_html_e( 'shows it as 1–12.', 'ean-to-pos' ); ?></li>
						<li><code>{Y}</code>, <code>{year}</code>, <code>{yyyy}</code> <?php esc_html_e( 'show a four-digit year such as 2026.', 'ean-to-pos' ); ?> <code>{y}</code>, <code>{yy}</code> <?php esc_html_e( 'show a two-digit year such as 26.', 'ean-to-pos' ); ?></li>
						<li><code>{d}</code> <?php esc_html_e( 'shows the day as 01–31.', 'ean-to-pos' ); ?> <code>{j}</code> <?php esc_html_e( 'shows it as 1–31.', 'ean-to-pos' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'For example, use S3/{number}/{month}/{year} with a minimum of 3 digits to get S3/007/11/2026. Use {number} with a minimum of 1 digit to get 7.', 'ean-to-pos' ); ?></p>
					<p><?php esc_html_e( 'Monthly formats need a month and year. Yearly formats need a year. Use separators such as / or - between numeric placeholders.', 'ean-to-pos' ); ?></p>
				</details>
				<?php
				self::sequence_fields( 'receipt', __( 'Receipt numbering', 'ean-to-pos' ), $settings['receipt'] );
				self::sequence_fields( 'invoice', __( 'Invoice numbering', 'ean-to-pos' ), $settings['invoice'] );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the inputs for one document series.
	 *
	 * @param mixed $type Document type key.
	 * @param mixed $label Visible field label.
	 * @param mixed $format Number format settings.
	 * @return mixed
	 */
	private static function sequence_fields( $type, $label, $format ) {
		?>
		<fieldset>
			<legend><strong><?php echo esc_html( $label ); ?></strong></legend>
			<p><label for="ean-to-pos-<?php echo esc_attr( $type ); ?>-pattern"><?php esc_html_e( 'Format', 'ean-to-pos' ); ?></label><br>
				<input class="regular-text code" id="ean-to-pos-<?php echo esc_attr( $type ); ?>-pattern" name="ean_to_pos_settings[<?php echo esc_attr( $type ); ?>][pattern]" type="text" maxlength="80" required value="<?php echo esc_attr( $format['pattern'] ); ?>">
			</p>
			<p><label for="ean-to-pos-<?php echo esc_attr( $type ); ?>-digits"><?php esc_html_e( 'Minimum sequence digits', 'ean-to-pos' ); ?></label><br>
				<input id="ean-to-pos-<?php echo esc_attr( $type ); ?>-digits" name="ean_to_pos_settings[<?php echo esc_attr( $type ); ?>][digits]" type="number" min="1" max="9" required value="<?php echo esc_attr( $format['digits'] ); ?>">
			</p>
			<p><label for="ean-to-pos-<?php echo esc_attr( $type ); ?>-start"><?php esc_html_e( 'First suggested sequence number', 'ean-to-pos' ); ?></label><br>
				<input id="ean-to-pos-<?php echo esc_attr( $type ); ?>-start" name="ean_to_pos_settings[<?php echo esc_attr( $type ); ?>][start]" type="number" min="1" max="999999999" required value="<?php echo esc_attr( $format['start'] ); ?>">
			</p>
			<p><label for="ean-to-pos-<?php echo esc_attr( $type ); ?>-reset"><?php esc_html_e( 'Reset sequence', 'ean-to-pos' ); ?></label><br>
				<select id="ean-to-pos-<?php echo esc_attr( $type ); ?>-reset" name="ean_to_pos_settings[<?php echo esc_attr( $type ); ?>][reset]">
					<?php foreach ( self::resets() as $key => $text ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $format['reset'] ); ?>><?php echo esc_html( $text ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</fieldset>
		<?php
	}
}
