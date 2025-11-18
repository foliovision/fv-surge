<?php if ( $total_files > $table_limit && ! isset( $_GET['show_all'] ) && ! isset( $_GET['show_list'] ) ) : ?>
	<p><?php echo esc_html( sprintf( __( 'Showing the %d most recent cache files. Click the "Show all" button to see all files.', 'surge' ), $table_limit ) ); ?></p>
	<p><a class="button" href="<?php echo esc_url( add_query_arg( 'show_all', '1' ) ); ?>"><?php esc_html_e( 'Show All', 'surge' ); ?></a></p>
<?php endif; ?>