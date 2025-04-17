<?php

/**
 * Settings page for Surge plugin
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
		add_options_page(
			__('Surge', 'surge'),
			__('Surge', 'surge'),
			'manage_options',
			'surge-settings',
			__NAMESPACE__ . '\render_settings_page'
		);
	}
);

/**
 * Parse configuration file content to extract settings
 * 
 * @param string $content The file content
 * @return array Array of settings
 */
function parse_config_content($content) {
	$settings = array();

	// Extract all $config assignments
	preg_match_all('/\$config\[[\'"](.*?)[\'"]\]\s*=\s*(.*?);/s', $content, $matches, PREG_SET_ORDER);

	foreach ($matches as $match) {
		$key = $match[1];
		$value = trim($match[2]);

		// Handle boolean values
		if ( $value === 'true' ) {
			$value = true;
		} elseif ( $value === 'false' ) {
			$value = false;
		}
		// Handle function calls
		elseif ( preg_match( '/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\(/', $value, $func_match ) ) {
			// Do not treat array_merge as a function call
			if ( $func_match[1] === 'array_merge' ) {

			} else {
				$value = $func_match[1] . '()';
			}
		}

		$settings[$key] = $value;
	}

	// Extract function definitions
	preg_match_all( '/function\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\(/', $content, $func_matches );

	if ( ! empty( $func_matches[1]) ) {
		$settings['_functions'] = $func_matches[1];
	}

	return $settings;
}

/**
 * Render the settings page
 */
function render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$config_path = defined('WP_CACHE_CONFIG') ? WP_CACHE_CONFIG : '';
	$config_content = '';
	$config = array();

	if ($config_path && file_exists($config_path)) {
		$config_content = file_get_contents($config_path);
		if ($config_content) {
			$config = parse_config_content($config_content);
		}
	}

?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<div class="card" style="max-width: 100%;">
			<h2><?php _e('Cache Configuration', 'surge'); ?></h2>

			<?php if ($config_path): ?>
				<p>
					<strong><?php _e( 'Configuration File:', 'surge' ); ?></strong><br>
					<code><?php echo esc_html( $config_path ); ?></code>
				</p>

				<?php if ( ! empty( $config ) ) : ?>
					<h3><?php _e('Configuration Settings', 'surge'); ?></h3>
					<table class="widefat" style="margin-top: 10px; width: 100%;">
						<thead>
							<tr>
								<th style="width: 30%;"><?php _e('Setting', 'surge'); ?></th>
								<th style="width: 70%;"><?php _e('Value', 'surge'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							// Group variants together
							$variants = array();
							$other_settings = array();

							foreach ($config as $key => $value) {
								if ($key === '_functions') {
									continue;
								}

								if (strpos($key, 'variants') === 0) {
									$variants[$key] = $value;
								} else {
									$other_settings[$key] = $value;
								}
							}

							// Display variants first
							if ( ! empty( $variants ) ) {
								echo '<tr><td colspan="2"><strong>' . __('Variants', 'surge') . '</strong></td></tr>';
								foreach ($variants as $key => $value) {
									$display_key = str_replace('variants', '', $key);
									$display_key = str_replace("']['", ' → ', $display_key);
									$display_key = str_replace("'", '', $display_key);
							?>
									<tr>
										<td><code><?php echo esc_html( $display_key ); ?></code></td>
										<td>
											<?php
											if ( is_bool( $value ) ) {
												echo $value ? 'true' : 'false';
											} elseif ( is_string( $value ) && strpos( $value, '()' ) !== false) {
												echo '<code>' . esc_html( $value ) . '</code>';
											} else {
												echo esc_html( $value );
											}
											?>
										</td>
									</tr>
								<?php
								}
							}

							// Display other settings
							foreach ($other_settings as $key => $value) {
								?>
								<tr>
									<td><code><?php echo esc_html( $key ); ?></code></td>
									<td>
										<?php
										if (is_bool($value)) {
											echo $value ? 'true' : 'false';
										} elseif (is_string($value) && strpos($value, '()') !== false) {
											echo '<code>' . esc_html( $value ) . '</code>';
										} else {
											echo esc_html( $value );
										}
										?>
									</td>
								</tr>
							<?php
							}
							?>
						</tbody>
					</table>

					<?php if (isset($config['_functions']) && !empty($config['_functions'])): ?>
						<h3 style="margin-top: 20px;"><?php _e('Custom Functions', 'surge'); ?></h3>
						<ul>
							<?php foreach ($config['_functions'] as $function): ?>
								<li><code><?php echo esc_html( $function ); ?>()</code></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php else: ?>
					<p><?php _e('No configuration settings found.', 'surge'); ?></p>
				<?php endif; ?>

				<?php if ( $config_content ) : ?>
					<h3 style="margin-top: 20px;"><?php _e('Raw Configuration File:', 'surge'); ?></h3>
					<pre style="background: #f0f0f1; padding: 10px; overflow: auto; max-height: 300px; width: 100%;"><?php echo esc_html($config_content); ?></pre>
				<?php else: ?>
					<p><?php _e( 'Configuration file exists but is empty or unreadable.', 'surge' ); ?></p>
				<?php endif; ?>
			<?php else: ?>
				<p><?php _e( 'This plugin has no settings. You have to create the PHP configuration file and store its path as <code>WP_CACHE_CONFIG</code> constant in <code>wp-config.php</code>.', 'surge' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
<?php
}
