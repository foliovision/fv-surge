<?php
/**
 * Surge Installer
 *
 * This file runs when Surge needs to be installed. Its main purpose is to copy
 * the advanced-cache.php loader and add the WP_CACHE and WP_CACHE_CONFIG constants to wp-config.php.
 *
 * @package Surge
 */

namespace Surge;

include_once( __DIR__ . '/common.php' );

// Remove old advanced-cache.php.
if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
	unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
}

// Copy our own advanced-cache.php.
$ret = copy( __DIR__ . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php' );
if ( ! $ret ) {
	update_option( 'surge_installed', 3 );
	return;
}

// Create the cache directory
wp_mkdir_p( CACHE_DIR );

$cookie_hash         = defined( 'COOKIEHASH' ) ? (string) constant( 'COOKIEHASH' ) : '';
$written_cookie_hash = get_option( 'surge_cookiehash_written', '' );

// Nothing to do if WP_CACHE is already on or forced skip.
if ( defined( 'WP_CACHE' ) && WP_CACHE && defined( 'WP_CACHE_CONFIG' ) && WP_CACHE_CONFIG && $cookie_hash === $written_cookie_hash || apply_filters( 'surge_skip_config_update', false ) ) {
	update_option( 'surge_installed', 1 );
	return;
}

// Fetch wp-config.php contents.
$config_path = ABSPATH . 'wp-config.php';
if ( ! file_exists( ABSPATH . 'wp-config.php' )
	&& @file_exists( dirname( ABSPATH ) . '/wp-config.php' )
	&& ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' )
) {
	$config_path = dirname( ABSPATH ) . '/wp-config.php';
}

// Create a default surge-cache-config.php if it does not exist.
$cache_config_path = dirname( $config_path ) . '/surge-cache-config.php';
if ( ! file_exists( $cache_config_path ) ) {
	$cache_config = sprintf(
		"<?php\n"
		. "/**\n"
		. " * FV Surge cache configuration.\n"
		. " *\n"
		. " * @package Surge\n"
		. " */\n\n"
		. "return [\n"
		. "\t'ignore_all_cookies_except' => [\n"
		. "\t\t'wordpress_logged_in_%s',\n"
		. "\t\t'comment_author_%s',\n"
		. "\t],\n"
		. "];\n",
		$cookie_hash,
		$cookie_hash
	);

	$cache_config_bytes = file_put_contents( $cache_config_path, $cache_config . "\n" );
	if ( false === $cache_config_bytes ) {
		update_option( 'surge_installed', 4 );
		return;
	}

	update_option( 'surge_cookiehash_written', $cookie_hash );

} else {
	if ( $cookie_hash !== $written_cookie_hash ) {
		$cache_config = file_get_contents( $cache_config_path );
		if ( false === $cache_config ) {
			update_option( 'surge_installed', 4 );
			return;
		}

		$cache_config_updated = preg_replace(
			'/\bwordpress_logged_in_[A-Za-z0-9]+\b/',
			'wordpress_logged_in_' . $cookie_hash,
			$cache_config
		);
		if ( null === $cache_config_updated ) {
			update_option( 'surge_installed', 4 );
			return;
		}

		$cache_config_updated = preg_replace(
			'/\bcomment_author_[A-Za-z0-9]+\b/',
			'comment_author_' . $cookie_hash,
			$cache_config_updated
		);
		if ( null === $cache_config_updated ) {
			update_option( 'surge_installed', 4 );
			return;
		}

		if ( $cache_config !== $cache_config_updated ) {
			$cache_config_bytes = file_put_contents( $cache_config_path, $cache_config_updated );
			if ( false === $cache_config_bytes ) {
				update_option( 'surge_installed', 4 );
				return;
			}
		}

		update_option( 'surge_cookiehash_written', $cookie_hash );
	}
}

// Fetch wp-config.php contents.
$config = file_get_contents( $config_path );

// Remove existing WP_CACHE and WP_CACHE_CONFIG cache definitions.
// Some regex inherited from https://github.com/wp-cli/wp-config-transformer/
$cache_constants = [ 'WP_CACHE', 'WP_CACHE_CONFIG' ];
foreach ( $cache_constants as $constant ) {
	$pattern = '#(?<=^|;|<\?php\s|<\?\s)(\s*?)(\h*define\s*\(\s*[\'"](' . preg_quote( $constant, '#' ) . ')[\'"]\s*)'
		. '(,\s*([\'"].*?[\'"]|.*?)\s*)((?:,\s*(?:true|false)\s*)?\)\s*;\s)#ms';

	$config = preg_replace( $pattern, '', $config );
}

// Add WP_CACHE and WP_CACHE_CONFIG defines to wp-config.php.
$cache_define_block = "define( 'WP_CACHE', true );\n"
	. "define( 'WP_CACHE_CONFIG', __DIR__ . '/surge-cache-config.php' );\n\n";
$anchor = "/* That's all, stop editing!";
if ( false !== strpos( $config, $anchor ) ) {
	$config = str_replace( $anchor, $cache_define_block . $anchor, $config );
} elseif ( false !== strpos( $config, '<?php' ) ) {
	$config = preg_replace( '#^<\?php\s.*#', "$0\n{$cache_define_block}", $config );
}

// Write modified wp-config.php.
$bytes = file_put_contents( $config_path, $config );
update_option( 'surge_installed', $bytes ? 1 : 2 );
