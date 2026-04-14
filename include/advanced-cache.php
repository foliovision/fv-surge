<?php
/**
 * Surge advanced-cache.php dropin
 *
 * @package Surge
 */

namespace Surge;

$filename = WP_CONTENT_DIR . '/plugins/fv-surge/include/serve.php';
if ( defined( 'WP_PLUGIN_DIR' ) ) {
	$filename = WP_PLUGIN_DIR . '/fv-surge/include/serve.php';
}

if ( file_exists( $filename ) ) {
	include_once( $filename );

// Fix plugin path if downloaded from Github
} else{
	$filename = WP_CONTENT_DIR . '/plugins/fv-surge-main/include/serve.php';
	if ( defined( 'WP_PLUGIN_DIR' ) ) {
		$filename = WP_PLUGIN_DIR . '/fv-surge-main/include/serve.php';
	}

	if ( file_exists( $filename ) ) {
		include_once( $filename );
	}
}
