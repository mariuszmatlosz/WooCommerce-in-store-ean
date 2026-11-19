<?php
/**
 * Unit tests for document format rules.
 *
 * @package EanToPos
 */

use EanToPos\Documents;

/**
 * Covers the formatter methods that do not call WordPress.
 */
class Documents_Test extends PHPUnit\Framework\TestCase {

	/**
	 * Formats one sequence with the configured pattern.
	 *
	 * @param array             $format   Pattern and minimum digits.
	 * @param int               $sequence Sequence to place in {number}.
	 * @param DateTimeInterface $date     Date used for the remaining tokens.
	 * @return string
	 */
	private function format_number( array $format, $sequence, DateTimeInterface $date ) {
		$method = new ReflectionMethod( Documents::class, 'format' );
		return $method->invoke( null, $format, $sequence, $date );
	}

	/**
	 * The pattern must contain one sequence and the date parts required by its reset.
	 */
	public function test_pattern_rules() {
		$this->assertTrue( Documents::valid_pattern( '{number}', 'continuous' ) );
		$this->assertTrue( Documents::valid_pattern( 'S3/{number}/{month}/{year}', 'monthly' ) );
		$this->assertTrue( Documents::valid_pattern( '{number}/{Y}', 'yearly' ) );
		$this->assertTrue( Documents::valid_pattern( '{number}/{n}/{j}/{year}', 'monthly' ) );

		$this->assertFalse( Documents::valid_pattern( '{number}', 'yearly' ) );
		$this->assertFalse( Documents::valid_pattern( '{number}', 'monthly' ) );
		$this->assertFalse( Documents::valid_pattern( '{number}/{year}', 'monthly' ) );
		$this->assertFalse( Documents::valid_pattern( '{number}{number}', 'continuous' ) );
		$this->assertFalse( Documents::valid_pattern( '{number}{n}', 'yearly' ) );
		$this->assertFalse( Documents::valid_pattern( '{Y}{year}/{number}', 'yearly' ) );
		$this->assertFalse( Documents::valid_pattern( '{foo}/{number}', 'continuous' ) );
		$this->assertFalse( Documents::valid_pattern( '', 'continuous' ) );
		$this->assertFalse( Documents::valid_pattern( str_repeat( 'A', 81 ), 'continuous' ) );
	}

	/**
	 * Aliases and minimum width are applied while the literal text stays intact.
	 */
	public function test_format_expands_tokens() {
		$date = new DateTimeImmutable( '2026-11-07' );

		$this->assertSame(
			'S3/007/11/2026',
			$this->format_number(
				array(
					'pattern' => 'S3/{number}/{month}/{year}',
					'digits'  => 3,
				),
				7,
				$date
			)
		);
		$this->assertSame(
			'7',
			$this->format_number(
				array(
					'pattern' => '{number}',
					'digits'  => 1,
				),
				7,
				$date
			)
		);
		$this->assertSame(
			'07-7-26',
			$this->format_number(
				array(
					'pattern' => '{d}-{j}-{yy}',
					'digits'  => 1,
				),
				7,
				$date
			)
		);
	}
}
