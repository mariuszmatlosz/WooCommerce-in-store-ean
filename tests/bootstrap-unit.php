<?php
/**
 * Loads the document formatter without WordPress.
 *
 * @package EanToPos
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require dirname( __DIR__ ) . '/includes/class-documents.php';
