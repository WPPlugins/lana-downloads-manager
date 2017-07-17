<?php

class Lana_Downloads_Manager_Logs_List_Table extends WP_List_Table{

	private $logs_per_page = 25;
	private $filter_month = '';

	/** @var Lana_Downloads_Manager_User_Agent_Parser $ua_parser */
	private $ua_parser = null;

	/** @var bool $display_delete_message */
	private $display_delete_message = false;

	/**
	 * __construct function
	 */
	public function __construct() {
		global $status, $page, $wpdb;

		parent::__construct( array(
			'singular' => 'downloads_manager_logs',
			'plural'   => 'downloads_manager_logs',
			'ajax'     => false
		) );

		$this->logs_per_page = ! empty( $_REQUEST['logs_per_page'] ) ? intval( $_REQUEST['logs_per_page'] ) : 25;
		$this->filter_month  = ! empty( $_REQUEST['filter_month'] ) ? sanitize_text_field( $_REQUEST['filter_month'] ) : '';
	}

	/**
	 * The checkbox column
	 *
	 * @param object $item
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="lana_download_log[]" value="%s" />', $item->id );
	}

	/**
	 * Add bulk actions
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'delete' => __( 'Delete', 'lana-downloads-manager' )
		);

		return $actions;
	}

	/**
	 * column_default function
	 *
	 * @param object $log
	 * @param string $column_name
	 *
	 * @return string|void
	 */
	public function column_default( $log, $column_name ) {
		switch ( $column_name ) {
			case 'date' :
				if ( empty( $log->download_date ) ) {
					return __( '(no date)', 'lana-downloads-manager' );
					break;
				}

				return '<time title="' . date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), strtotime( $log->download_date ) ) . '"">' . sprintf( __( '%s ago', 'lana-downloads-manager' ), human_time_diff( strtotime( $log->download_date ), current_time( 'timestamp' ) ) ) . '</time>';
				break;
			case 'download' :
				$lana_download = get_post( $log->download_id );
				$title         = get_the_title( $lana_download );

				if ( $title ) {
					$download_string = '<a href="' . admin_url( 'post.php?post=' . $lana_download->ID . '&action=edit' ) . '">';
					$download_string .= '#' . $lana_download->ID . ' &ndash; ' . get_the_title( $lana_download );
					$download_string .= '</a>';

					return $download_string;
				} else {
					$download_string = '#' . $log->download_id . ' &ndash; ' . __( '(no longer exists)', 'lana-downloads-manager' );

					return $download_string;
				}

				break;
			case 'user' :
				if ( $log->user_id ) {
					$user = get_user_by( 'id', $log->user_id );
				}

				if ( ! isset( $user ) || ! $user || empty( $user ) ) {
					$user_string = __( 'Non-member', 'lana-downloads-manager' );
				} else {
					$user_string = '<a href="' . admin_url( 'user-edit.php?user_id=' . $user->ID ) . '">';
					$user_string .= $user->user_login . ' &ndash; ';
					$user_string .= '<a href="mailto:' . $user->user_email . '">';
					$user_string .= $user->user_email;
					$user_string .= '</a>';
				}

				return $user_string;
				break;
			case 'user_ip' :
				return $log->user_ip;
				break;
			case 'user_ua' :

				$ua = $this->ua_parser->parse( $log->user_agent );

				return $ua->to_full_string;
				break;
		}
	}

	/**
	 * get_columns function
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'       => '',
			'download' => __( 'Download', 'lana-downloads-manager' ),
			'user'     => __( 'User', 'lana-downloads-manager' ),
			'user_ip'  => __( 'IP Address', 'lana-downloads-manager' ),
			'user_ua'  => __( 'User Agent', 'lana-downloads-manager' ),
			'date'     => __( 'Date', 'lana-downloads-manager' ),
		);

		return $columns;
	}

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @param string $which
	 */
	public function display_tablenav( $which ) {

		if ( 'top' == $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );
		}

		if ( 'top' == $which && true === $this->display_delete_message ) {
			?>
            <div id="message" class="updated notice notice-success">
                <p><?php _e( 'Log entries deleted', 'lana-downloads-manager' ); ?></p>
            </div>
			<?php
		}
		?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">

            <div class="alignleft actions bulkactions">
				<?php $this->bulk_actions( $which ); ?>
            </div>

			<?php if ( 'top' == $which ) : ?>
                <div class="alignleft actions">
					<?php
					global $wpdb, $wp_locale;

					$table_name  = $wpdb->prefix . 'lana_downloads_manager_logs';
					$months      = $wpdb->get_results( "SELECT DISTINCT YEAR( download_date ) AS year, MONTH( download_date ) AS month FROM " . $table_name . " ORDER BY download_date DESC" );
					$month_count = count( $months );

					if ( $month_count && ! ( 1 == $month_count && 0 == $months[0]->month ) ) :
						$m = isset( $_GET['filter_month'] ) ? $_GET['filter_month'] : 0;
						?>
                        <select name="filter_month">
                            <option <?php selected( $m, 0 ); ?> value='0'>
								<?php _e( 'Show all dates', 'lana-downloads-manager' ); ?>
                            </option>
							<?php
							foreach ( $months as $arc_row ) {
								if ( 0 == $arc_row->year ) {
									continue;
								}

								$month = zeroise( $arc_row->month, 2 );
								$year  = $arc_row->year;

								printf( '<option %s value="%s">%s</option>\n', selected( $m, $year . '-' . $month, false ), esc_attr( $year . '-' . $month ), sprintf( __( '%1$s %2$d' ), $wp_locale->get_month( $month ), $year ) );
							}
							?>
                        </select>
					<?php endif;
					?>
                    <select name="logs_per_page">
                        <option value="25">
							<?php _e( '25 per page', 'lana-downloads-manager' ); ?>
                        </option>
                        <option value="50" <?php selected( $this->logs_per_page, 50 ); ?>>
							<?php _e( '50 per page', 'lana-downloads-manager' ); ?>
                        </option>
                        <option value="100" <?php selected( $this->logs_per_page, 100 ); ?>>
							<?php _e( '100 per page', 'lana-downloads-manager' ); ?>
                        </option>
                    </select>
                    <input type="hidden" name="post_type" value="lana-download"/>
                    <input type="hidden" name="page" value="lana-downloads-manager-logs"/>
                    <input type="submit" value="<?php esc_attr_e( 'Filter', 'lana-downloads-manager' ); ?>"
                           class="button"/>
                </div>
			<?php endif; ?>
			<?php
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>
            <br class="clear"/>
        </div>
		<?php
	}

	/**
	 * prepare_items function
	 */
	public function prepare_items() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'lana_downloads_manager_logs';

		$this->process_bulk_action();

		$per_page     = absint( $this->logs_per_page );
		$current_page = absint( $this->get_pagenum() );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$query_where = "";

		if ( $this->filter_month ) {
			$query_where = " WHERE download_date >= '" . date( 'Y-m-01', strtotime( $this->filter_month ) ) . "' ";
			$query_where .= " AND download_date <= '" . date( 'Y-m-t', strtotime( $this->filter_month ) ) . "' ";
		}

		$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM " . $table_name . " " . $query_where . ";" );
		$this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $table_name . " " . $query_where . " ORDER BY download_date DESC LIMIT %d, %d;", ( $current_page - 1 ) * $per_page, $per_page ) );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ( ( $total_items > 0 ) ? ceil( $total_items / $per_page ) : 1 )
		) );

		if ( ! class_exists( 'Lana_Downloads_Manager_User_Agent_Parser' ) ) {
			require_once( 'class-lana-downloads-manager-user-agent-parser.php' );
		}

		$this->ua_parser = new Lana_Downloads_Manager_User_Agent_Parser();
	}

	/**
	 * Process bulk actions
	 */
	public function process_bulk_action() {

		if ( 'delete' === $this->current_action() ) {

			if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) ) {
				wp_die( __( 'process_bulk_action() nonce check failed' ) );
			}

			if ( ! current_user_can( 'manage_lana_download_logs' ) ) {
				wp_die( __( 'You\'re not allowed to delete logs!' ) );
			}

			if ( count( $_POST['lana_download_log'] ) > 0 ) {

				foreach ( $_POST['lana_download_log'] as $log_id ) {
					global $wpdb;

					$table_name = $wpdb->prefix . 'lana_downloads_manager_logs';
					$wpdb->delete( $table_name, array( 'id' => $log_id ) );
				}

				$this->display_delete_message = true;

			}

		}

	}
}