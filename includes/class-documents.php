<?php
/**
 * Document references and sequence counters.
 *
 * @package EanToPos
 */

namespace EanToPos;

defined( 'ABSPATH' ) || exit;

/**
 * Document numbers and the counters that keep them unique.
 */
final class Documents {

	private const SEQUENCES   = 'ean_to_pos_sequences';
	public const MAX_SEQUENCE = 999999999;

	// Aliases map to PHP date format characters.
	private const ALIASES     = array(
		'month' => 'm',
		'year'  => 'Y',
		'yy'    => 'y',
		'yyyy'  => 'Y',
	);
	private const DATE_FIELDS = array(
		'Y' => array( 'year', '[0-9]{4}' ),
		'y' => array( 'year', '[0-9]{2}' ),
		'm' => array( 'month', '0[1-9]|1[0-2]' ),
		'n' => array( 'month', '[1-9]|1[0-2]' ),
		'd' => array( 'day', '0[1-9]|[12][0-9]|3[01]' ),
		'j' => array( 'day', '[1-9]|[12][0-9]|3[01]' ),
	);

	/**
	 * Labels for the document types, including none.
	 *
	 * @return mixed
	 */
	public static function labels() {
		return array(
			'receipt' => __( 'Receipt', 'ean-to-pos' ),
			'invoice' => __( 'Invoice', 'ean-to-pos' ),
			''        => __( 'None', 'ean-to-pos' ),
		);
	}

	/**
	 * Document types currently available in the register.
	 *
	 * @return mixed
	 */
	public static function types() {
		$types = self::labels();
		if ( ! Settings::get()['invoices_enabled'] ) {
			unset( $types['invoice'] );
		}
		return $types;
	}

	/**
	 * Suggested next number for each available document type.
	 *
	 * @return mixed
	 */
	public static function suggestions() {
		$result = array();
		foreach ( self::types() as $type => $label ) {
			if ( '' !== $type ) {
				$result[ $type ] = self::next_number( $type );
			}
		}
		return $result;
	}

	/**
	 * Format the configured starting sequence for today.
	 *
	 * @param mixed $type Document type key.
	 * @return mixed
	 */
	public static function starting_number( $type ) {
		$format = Settings::get()[ $type ];
		return self::format( $format, $format['start'], current_datetime() );
	}

	/**
	 * Return the next number in a series.
	 *
	 * @param mixed $type Document type key.
	 * @param mixed $current Number currently shown in the form.
	 * @return mixed
	 */
	public static function next_number( $type, $current = '' ) {
		$format = Settings::get()[ $type ];
		$parsed = self::parse( $format, $current );
		if ( $parsed && $parsed['sequence'] > 0 ) {
			$date = $parsed['date'];
			$next = $parsed['sequence'] + 1;
		} else {
			$date     = current_datetime();
			$counters = get_option( self::SEQUENCES, array() );
			$scope    = self::scope( $format['reset'], $date );
			$next     = max( $format['start'], 1 + (int) ( $counters[ $type ][ $scope ] ?? 0 ) );
		}

		return $next <= self::MAX_SEQUENCE ? self::format( $format, $next, $date ) : '';
	}

	/**
	 * Turn a sequence or full reference into the configured format.
	 *
	 * @param mixed $type Document type key.
	 * @param mixed $number Submitted document number.
	 * @return mixed
	 * @throws \DomainException When the number does not match the format.
	 */
	public static function normalize( $type, $number ) {
		if ( '' === $type ) {
			return '';
		}

		$format = Settings::get()[ $type ];
		$parsed = self::parse( $format, $number );
		if ( ! $parsed || $parsed['sequence'] < 1 ) {
			throw new \DomainException(
				/* translators: %s: configured document number format. */
				sprintf( esc_html__( 'Enter a positive sequence number or a full reference matching %s.', 'ean-to-pos' ), esc_html( $format['pattern'] ) )
			);
		}
		return self::format( $format, $parsed['sequence'], $parsed['date'] );
	}

	/**
	 * Whether a format contains one sequence and the required date parts.
	 *
	 * @param mixed $pattern Format pattern.
	 * @param mixed $reset Reset cycle.
	 * @return mixed
	 */
	public static function valid_pattern( $pattern, $reset ) {
		if ( strlen( $pattern ) > 80 ) {
			return false;
		}

		$seen           = array();
		$variable_width = false;
		foreach ( self::parts( $pattern ) as $part ) {
			if ( ! strpbrk( $part, '{}' ) ) {
				if ( preg_match( '/[^0-9]/', $part ) ) {
					$variable_width = false;
				}
				continue;
			}
			$token = self::token( $part );
			$field = 'number' === $token ? 'number' : ( self::DATE_FIELDS[ $token ][0] ?? '' );
			if ( '' === $field || isset( $seen[ $field ] ) ) {
				return false;
			}
			if ( in_array( $token, array( 'number', 'n', 'j' ), true ) ) {
				if ( $variable_width ) {
					return false;
				}
				$variable_width = true;
			}
			$seen[ $field ] = true;
		}

		return isset( $seen['number'] )
			&& ( 'continuous' === $reset || isset( $seen['year'] ) )
			&& ( 'monthly' !== $reset || isset( $seen['month'] ) );
	}

	/**
	 * Split a pattern into literals and tokens.
	 *
	 * @param mixed $pattern Format pattern.
	 * @return mixed
	 */
	private static function parts( $pattern ) {
		return preg_split( '/(\{[A-Za-z]+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE );
	}

	/**
	 * Return the date or number token inside a pattern part.
	 *
	 * @param mixed $part Pattern fragment.
	 * @return mixed
	 */
	private static function token( $part ) {
		if ( ! preg_match( '/\A\{([A-Za-z]+)\}\z/', $part, $match ) ) {
			return '';
		}
		return self::ALIASES[ $match[1] ] ?? $match[1];
	}

	/**
	 * Read a sequence and date from a number or a full reference.
	 *
	 * @param mixed $format Number format settings.
	 * @param mixed $number Submitted document number.
	 * @return mixed
	 */
	private static function parse( $format, $number ) {
		$today = current_datetime();
		$regex = '';
		$short = false;
		foreach ( self::parts( $format['pattern'] ) as $part ) {
			$token = self::token( $part );
			if ( 'number' === $token ) {
				$regex .= '(?<sequence>[0-9]{1,9})';
			} elseif ( isset( self::DATE_FIELDS[ $token ] ) ) {
				list( $field, $pattern ) = self::DATE_FIELDS[ $token ];
				$regex                  .= '(?<' . $field . '>' . $pattern . ')';
				$short                   = $short || 'y' === $token;
			} else {
				$regex .= preg_quote( $part, '~' );
			}
		}

		if ( preg_match( '~\A' . $regex . '\z~', $number, $match ) ) {
			$year  = (int) ( $match['year'] ?? $today->format( 'Y' ) );
			$month = (int) ( $match['month'] ?? $today->format( 'm' ) );
			$day   = (int) ( $match['day'] ?? 1 );
			if ( $short ) {
				$year += 100 * intdiv( (int) $today->format( 'Y' ), 100 );
			}
			if ( ! checkdate( $month, $day, $year ) ) {
				return null;
			}
			return array(
				'sequence' => (int) $match['sequence'],
				'date'     => $today->setDate( $year, $month, $day ),
			);
		}

		if ( preg_match( '/\A[0-9]{1,9}\z/', $number ) ) {
			return array(
				'sequence' => (int) $number,
				'date'     => $today,
			);
		}
		return null;
	}

	/**
	 * Apply a sequence and date to a pattern.
	 *
	 * @param mixed $format Number format settings.
	 * @param mixed $sequence Sequence value.
	 * @param mixed $date Date used for the format.
	 * @return mixed
	 */
	private static function format( $format, $sequence, $date ) {
		$result = '';
		foreach ( self::parts( $format['pattern'] ) as $part ) {
			$token = self::token( $part );
			if ( 'number' === $token ) {
				$result .= str_pad( (string) $sequence, $format['digits'], '0', STR_PAD_LEFT );
			} else {
				$result .= isset( self::DATE_FIELDS[ $token ] ) ? $date->format( $token ) : $part;
			}
		}
		return $result;
	}

	/**
	 * Counter bucket for a reset cycle and date.
	 *
	 * @param mixed $reset Reset cycle.
	 * @param mixed $date Date used for the format.
	 * @return mixed
	 */
	private static function scope( $reset, $date ) {
		if ( 'monthly' === $reset ) {
			return $date->format( 'm/Y' );
		}
		return 'yearly' === $reset ? $date->format( 'Y' ) : 'continuous';
	}

	/**
	 * Store the document number and create the WooCommerce order.
	 *
	 * @param mixed $items Reviewed product lines.
	 * @param mixed $data Submitted register fields.
	 * @param mixed $address Billing address.
	 * @return mixed
	 * @throws \DomainException When the number is not the next one in its series.
	 * @throws \RuntimeException When the counter cannot be saved.
	 * @throws \Exception When creating the order fails.
	 */
	public static function record_sale( $items, $data, $address ) {
		$type = $data['document_type'];
		if ( '' === $type ) {
			return Register::order( $items, $data, $address );
		}

		$lock = self::SEQUENCES . '_lock';
		if ( ! add_option( $lock, 'claimed', '', false ) ) {
			throw new \DomainException( esc_html__( 'Another sale is saving a document number. Review the sale again in a moment.', 'ean-to-pos' ) );
		}

		try {
			$format = Settings::get()[ $type ];
			$parsed = self::parse( $format, $data['document_number'] );
			if ( ! $parsed ) {
				throw new \DomainException( esc_html__( 'The document format changed. Review the sale again.', 'ean-to-pos' ) );
			}
			$scope    = self::scope( $format['reset'], $parsed['date'] );
			$previous = get_option( self::SEQUENCES, array() );
			$last     = (int) ( $previous[ $type ][ $scope ] ?? 0 );
			if ( $parsed['sequence'] <= $last ) {
				throw new \DomainException(
					/* translators: %s: last recorded document number. */
					sprintf( esc_html__( 'This number must be higher than %s in the selected series. Choose the next available number and review the sale again.', 'ean-to-pos' ), esc_html( self::format( $format, $last, $parsed['date'] ) ) )
				);
			}

			$next                    = $previous;
			$next[ $type ][ $scope ] = $parsed['sequence'];
			if ( ! update_option( self::SEQUENCES, $next, false ) ) {
				throw new \RuntimeException();
			}

			try {
				return Register::order( $items, $data, $address );
			} catch ( \Exception $error ) {
				update_option( self::SEQUENCES, $previous, false );
				throw $error;
			}
		} finally {
			delete_option( $lock );
		}
	}
}
