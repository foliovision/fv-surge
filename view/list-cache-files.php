<?php
/**
 * Cache List page for Surge plugin
 *
 * @package Surge
 */

namespace Surge;

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

// Add menu item
add_action(
	'admin_menu',
	function() {
		add_management_page(
			__('Surge Cache List', 'surge'),
			__('Surge', 'surge'),
			'manage_options',
			'surge-cache-list',
			__NAMESPACE__ . '\render_cache_list_page'
		);
	}
);

/**
 * Render the cache list page
 */
function render_cache_list_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Set execution time to 5 minutes to handle large cache directories
	set_time_limit(300);

	// Define the cache directory path
	$cache_dir = WP_CONTENT_DIR . '/cache/surge';

	// Check if the directory exists
	if ( ! is_dir( $cache_dir ) ) {
		echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1>';
		echo '<div class="notice notice-error"><p>' . esc_html(sprintf(__('Error: Cache directory not found at %s', 'surge'), $cache_dir)) . '</p></div></div>';
		return;
	}

	/**
	 * Single Surge PHP cache files start with <?php exit; ?> followed by some junk, then JSON and then HTML,
	 * we need to extract the JSON from the file.
	 *
	 * We read the first 100000 bytes of the file to find the JSON data.
	 * We could read less, but then we might not get the whole JSON data.
	 *
	 * @param string $file_path The path to the cache file
	 *
	 * @return string The JSON data from the file
	 */
	function get_data_from_surge_cache_file( $file_path ) {
		$json_str = '';

		// Read the first part of the file (we only need the header)
		$content = file_get_contents( $file_path, false, null, 0, 100000 );
		
		// Find the position of the JSON data start
		$start_pos  = strpos( $content, '<?php exit; \?\>') + strlen('<?php exit; \?\>' );
		$json_start = strpos ($content, '{', $start_pos );

		if ( $json_start !== false ) {
			// JSON ends where cached HTML starts
			$html_start = strpos($content, '<!DOCTYPE html>', $json_start);
			
			if ( $html_start !== false ) {
				$json_str = substr($content, $json_start, $html_start - $json_start);
			} else {
				// ...or JSON ends where cached XML starts
				$xml_start = strpos($content, '<?xml ', $json_start);

				if ( $xml_start !== false ) {
					$json_str = substr($content, $json_start, $xml_start - $json_start);
				} else {
					$json_str = substr($content, $json_start);
				}
			}
		} else {
			echo '<div class="notice notice-warning"><p>' . esc_html(sprintf(__('Could not find JSON data in file %s', 'surge'), $file_path)) . '</p></div>';
		}

		$json = json_decode($json_str, true);

		if (!$json) {
			echo '<div class="notice notice-warning"><p>' . esc_html(sprintf(__('Could not decode JSON data in file %s', 'surge'), $file_path)) . '</p></div>';
		}

		return $json;
	}

	// Function to recursively scan directories
	function scanDirectory( $dir ) {
		$files = [];
		$items = scandir($dir);
		
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			if ('flags.json.php' === $item) {
				continue;
			}
			
			$path = $dir . '/' . $item;
			
			if (is_dir($path)) {
				$files = array_merge( $files, scanDirectory( $path ) );
			} else {
				$files[] = $path;
			}
		}
		
		// Sort files by modification time, newest first
		usort( $files, function( $a, $b ) {
			return filemtime( $b ) - filemtime( $a );
		} );
		
		return $files;
	}

	// Get all cache files
	$cache_files = scanDirectory( $cache_dir );
	$total_files = count( $cache_files );

	// Process each file
	$results = array();

	foreach ( $cache_files as $file ) {
		// Decode the JSON data
		$data = get_data_from_surge_cache_file( $file );

		if ($data && isset($data['path'])) {
			$url = $data['path'];

			// Check if it's a mobile version
			$is_mobile = false;
			if (
				isset( $data['headers']['Vary'] ) && 
				is_array( $data['headers']['Vary'] ) && 
				in_array( 'User-Agent', $data['headers']['Vary'] )
			) {
				$is_mobile = true;
			}

			$results[] = [
				'url'        => $url,
				'is_mobile'  => $is_mobile,
				'file'       => $file,
				'code'       => $data['code'],
				'debug'      => $data['debug'],
				'expires'    => $data['expires'],
			];
		}
	}

	// Sort restults by expires
	usort($results, function($a, $b) {
		return $b['expires'] - $a['expires'];
	});

	$count_expired = 0;
	foreach ($results as $result) {
		if ($result['expires'] < time()) {
			$count_expired++;
		}
	}
?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<style>
		.expired { color: red; }
		.mobile { color: blue; }
		.tooltip { position: relative; }
		.tooltip .tooltip-content { 
			visibility: hidden; 
			width: 500px; 
			background-color: #f9f9f9; 
			color: #333; 
			text-align: left; 
			border-radius: 6px; 
			padding: 10px; 
			position: absolute; 
			right: -500px; 
			z-index: 1; 
			border: 1px solid #ddd; 
			box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
			margin-top: 2em 
		}
		.tooltip .tooltip-content pre { 
			margin: 0; 
			white-space: pre-wrap; 
			font-family: monospace; 
			font-size: 12px; 
		}
		.tooltip:hover .tooltip-content { 
			visibility: visible;
		}
		.tablesorter-headerAsc:after { content: ' ▲'; }
		.tablesorter-headerDesc:after { content: ' ▼'; }
		</style>
      
		<div class="notice notice-info">
			<p><?php echo esc_html( sprintf( __( 'Found %d cache files.', 'surge' ), $total_files ) ); ?></p>
			<p><?php echo esc_html( sprintf( __( 'Total expired: %d', 'surge' ), $count_expired ) ); ?></p>
			<p><?php echo esc_html( sprintf( __( 'Peak memory usage: %s MB', 'surge' ), number_format( memory_get_peak_usage() / 1024 / 1024, 2 ) ) ); ?></p>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'URL', 'surge' ); ?></th>
					<th scope="col" style="width: 5em;"><?php esc_html_e( 'Code', 'surge' ); ?></th>
					<th scope="col" style="width: 5em;"><?php esc_html_e( 'Mobile', 'surge' ); ?></th>
					<th scope="col" style="width: 5em;"><?php esc_html_e( 'Expired', 'surge' ); ?></th>
					<th scope="col" style="width: 10em;"><?php esc_html_e( 'Created', 'surge' ); ?></th>
					<th scope="col" style="width: 10em;"><?php esc_html_e( 'Expires', 'surge' ); ?></th>
					<th scope="col" style="width: 5em;"><?php esc_html_e( 'Actions', 'surge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$current_site = '';
				foreach ($results as $result) {
					$expired = $result['expires'] < time();
					$expired_class = $expired ? ' class="expired"' : '';
					
					$query_vars = array();
					if ( ! empty( $result['debug']['query_vars'] ) ) {
						foreach ( $result['debug']['query_vars'] as $key => $value ) {
							if ( empty( $key ) ) {
								continue;
							}
							$query_var = $key;
							if ( ! empty( $value ) ) {
								$query_var .= "=" . $value;
							}
							$query_vars[] = $query_var;
						}
					}

					$full_url = $result['url'];
					if ( ! empty( $query_vars ) ) {
						$full_url .= '?' . implode( '&', $query_vars );
					}
					
					$mod_time = date( 'Y-m-d H:i:s', filemtime( $result['file'] ) );
					$expires_time = date( 'Y-m-d H:i:s', $result['expires'] );

					unset( $result['debug']['query_vars'] );
					unset( $result['debug']['path'] );
					
					$debug_info = json_encode( $result['debug'], JSON_PRETTY_PRINT );
					$file_path = str_replace( $cache_dir . '/', '', $result['file'] );
					
					echo "<tr{$expired_class}>\n";
					echo "<td class='url tooltip'>{$full_url}<span class='tooltip-content'>" . esc_html( $file_path ) . "<pre>" . esc_html($debug_info) . "</pre></span></td>\n";
					echo "<td class='code'>{$result['code']}</td>\n";
					echo "<td class='mobile'>" . ($result['is_mobile'] ? esc_html__('Mobile', 'surge') : '') . "</td>\n";
					echo "<td class='expired'>" . ($expired ? esc_html__('Expired', 'surge') : '') . "</td>\n";
					echo "<td class='time'>{$mod_time}</td>\n";
					echo "<td class='expires'>{$expires_time}</td>\n";
					echo "<td class='actions'>";
					echo "<button class='button button-small delete-cache' data-file_path='" . esc_attr( $file_path ) . "'>" . esc_html__( 'Purge', 'surge' ) . "</button>";
					echo "</td>\n";
					echo "</tr>\n";
				}
				?>
			</tbody>
		</table>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Handle cache deletion via AJAX
			$('.delete-cache').on('click', function(e) {
				e.preventDefault();
				
				var button = $(this);
				button.prop('disabled', true);
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action:    'surge_delete_cache',
						file_path: button.data('file_path'),
						nonce:     '<?php echo wp_create_nonce('surge_delete_cache'); ?>'
					},
					success: function(response) {
						if (response.success) {
							button.closest('tr').fadeOut(400, function() {
								$(this).remove();
							});
						} else {
							alert(response.data);
							button.prop('disabled', false);
						}
					},
					error: function() {
						alert('<?php esc_html_e('Error occurred while deleting the cache file.', 'surge'); ?>');
						button.prop('disabled', false);
					}
				});
			});
		});
	</script>

	<script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.3/js/jquery.tablesorter.min.js'></script>
	<script type='text/javascript'>
	jQuery( function($) {
		$('table').tablesorter({
			sortList: [[5,1]],
			headers: {
				0: { sorter: 'text' },
				1: { sorter: 'digit' },
				2: { sorter: 'text' },
				3: { sorter: 'text' },
				4: { sorter: 'datetime' }
			}
		});
	});
	</script>
<?php
}

// Handle cache deletion
add_action(
	'wp_ajax_surge_delete_cache',
	function() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions to access this page.', 'surge' ) );
		}

		if ( ! isset( $_POST['file_path'] ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'surge_delete_cache' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'surge' ) );
		}

		$file_path = $_POST['file_path'];

		// Validate file path format
		if ( ! is_string( $file_path ) || ! preg_match( '/^[a-f0-9]{2}\/[a-f0-9]{32}\.php$/', $file_path ) ) {
			wp_send_json_error( __( 'Invalid file path format.', 'surge' ) );
		}
		
		// Security check to ensure the file is within the cache directory
		$cache_dir = WP_CONTENT_DIR . '/cache/surge';
		$full_path = trailingslashit( $cache_dir ) . $file_path;

		// Additional security check to ensure the resolved path is still within cache directory
		$real_path = realpath( $full_path );
		$cache_dir_real = realpath( $cache_dir );

		if ( $real_path === false || strpos( $real_path, $cache_dir_real ) !== 0 ) {
			wp_send_json_error( __( 'Invalid file path.', 'surge' ) );
		}

		if ( file_exists( $full_path ) && unlink( $full_path ) ) {
			wp_send_json_success( __( 'Cache file deleted successfully.', 'surge' ) );
		} else {
			wp_send_json_error( __( 'Error deleting cache file.', 'surge' ) );
		}
	}
);