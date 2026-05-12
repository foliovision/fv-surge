<?php
/**
 * Admin bar control to purge the full Surge page cache.
 *
 * @package Surge
 */

namespace Surge;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once __DIR__ . '/common.php';

add_action(
	'admin_bar_menu',
	function ( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) || class_exists( 'BusinessPress_Surge_Cache_Purge' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'surge-cache-purge',
				'title' => __( 'Surge Purge', 'surge' ),
				'href'  => '#',
			)
		);
	},
	999
);

add_action( 'admin_footer', __NAMESPACE__ . '\admin_bar_purge_print_script', 100 );
add_action( 'wp_footer', __NAMESPACE__ . '\admin_bar_purge_print_script', 100 );

/**
 * Outputs jQuery handler for the Surge Purge admin bar item (admin and front-end).
 */
function admin_bar_purge_print_script() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$ajax_url = admin_url( 'admin-ajax.php' );
	$nonce    = wp_create_nonce( 'surge-purge-full-cache' );
	?>
	<script>
	jQuery( function( $ ) {
		var purging = false;
		var defaultLabel = <?php echo wp_json_encode( __( 'Surge Purge', 'surge' ) ); ?>;

		$( '#wp-admin-bar-surge-cache-purge' ).on(
			'click',
			function( e ) {
				e.preventDefault();
				var $row = $( this );
				var $link = $row.find( 'a.ab-item' ).length ? $row.find( 'a.ab-item' ) : $row.find( 'a' );

				if ( purging ) {
					return false;
				}

				purging = true;
				$link.text( <?php echo wp_json_encode( __( 'Purging…', 'surge' ) ); ?> );

				$.post(
					<?php echo wp_json_encode( $ajax_url ); ?>,
					{
						action: 'surge_purge_full_cache',
						nonce: <?php echo wp_json_encode( $nonce ); ?>
					},
					function( response ) {
						purging = false;

						if ( response.success ) {
							$link.text( response.data );
							setTimeout( function() {
								$link.text( defaultLabel );
							}, 2000 );
						} else {
							$link.text( defaultLabel );

							if ( response.data ) {
								alert( response.data );
							} else {
								alert( <?php echo wp_json_encode( __( 'Surge cache purge failed.', 'surge' ) ); ?> );
							}
						}
					}
				);

				return false;
			}
		);
	} );
	</script>
	<?php
}

add_action(
	'wp_ajax_surge_purge_full_cache',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions to purge the cache.', 'surge' ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'surge-purge-full-cache' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'surge' ) );
		}

		if ( is_dir( CACHE_DIR ) ) {
			require_once \ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once \ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

			$fs = new \WP_Filesystem_Direct( false );
			$r  = $fs->rmdir( CACHE_DIR, true );

			if ( ! $r ) {
				wp_send_json_error( __( 'Surge cache folder could not be deleted. Please check file permissions.', 'surge' ) );
			}
		}

		wp_send_json_success( __( 'Success!', 'surge' ) );
	}
);
