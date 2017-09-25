<?php

/**
 * EDD MalChimp helper functions
 *
 * @copyright   Copyright (c) 2014-2017, Easy Digital Downloads
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
*/

if( ! function_exists( 'edd_debug_log' ) ) {
	function edd_debug_log( $message = '' ) {
		error_log( $message, 3,  trailingslashit( wp_upload_dir() ) . 'edd-debug-log.txt' );
	}
}