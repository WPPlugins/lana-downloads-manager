<?php
/**
 * Plugin Name: Lana Downloads Manager
 * Plugin URI: http://wp.lanaprojekt.hu/blog/wordpress-plugins/lana-downloads-manager/
 * Description: Downloads Manager with counter and log.
 * Version: 1.1.0
 * Author: Lana Design
 * Author URI: http://wp.lanaprojekt.hu/blog/
 */

defined( 'ABSPATH' ) or die();
define( 'LANA_DOWNLOADS_MANAGER_VERSION', '1.1.0' );

/**
 * Language
 * load
 */
load_plugin_textdomain( 'lana-downloads-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

/**
 * Plugin Settings link
 *
 * @param $links
 *
 * @return mixed
 */
function lana_downloads_manager_plugin_settings_link( $links ) {
	$settings_link = '<a href="edit.php?post_type=lana-download&page=lana-downloads-manager-settings">' . __( 'Settings', 'lana-downloads-manager' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'lana_downloads_manager_plugin_settings_link' );

/**
 * Lana Download Widget
 */
include_once 'includes/class-lana-download-widget.php';
add_action( 'widgets_init', function () {
	register_widget( 'Lana_Download_Widget' );
} );

/**
 * Install Lana Downloads Manager
 * - create dir
 * - create log table
 */
function lana_downloads_manager_install() {
	lana_downloads_manager_create_upload_directory();
	lana_downloads_manager_create_logs_table();
}

register_activation_hook( __FILE__, 'lana_downloads_manager_install' );

/**
 * Create logs table
 */
function lana_downloads_manager_create_logs_table() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'lana_downloads_manager_logs';

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$sql = "CREATE TABLE " . $table_name . " (
	  id bigint(20) NOT NULL auto_increment,
	  user_id bigint(20) DEFAULT NULL,
	  user_ip varchar(255) NOT NULL,
	  user_agent varchar(255) NOT NULL,
	  download_id bigint(20) NOT NULL,
	  download_date datetime DEFAULT NULL,
	  PRIMARY KEY (id),
	  KEY attribute_name (download_id)
	) " . $charset_collate . ";";

	dbDelta( $sql );
}

/**
 * Create upload directory
 */
function lana_downloads_manager_create_upload_directory() {

	$upload_dir = wp_upload_dir();

	$files = array(
		array(
			'base'    => $upload_dir['basedir'] . '/lana-downloads',
			'file'    => '.htaccess',
			'content' => lana_downloads_manager_get_upload_directory_htaccess()
		),
		array(
			'base'    => $upload_dir['basedir'] . '/lana-downloads',
			'file'    => 'index.php',
			'content' => ''
		)
	);

	foreach ( $files as $file ) {
		if ( wp_mkdir_p( $file['base'] ) ) {
			if ( $file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' ) ) {
				fwrite( $file_handle, $file['content'] );
				fclose( $file_handle );
			}
		}
	}

	flush_rewrite_rules();
}

/**
 * Get upload directory .htaccess
 * @return string
 */
function lana_downloads_manager_get_upload_directory_htaccess() {

	$htaccess = 'deny from all' . PHP_EOL;
	$htaccess .= '<FilesMatch "\.(jpg|jpeg|png|gif)$">' . PHP_EOL;
	$htaccess .= '  allow from all' . PHP_EOL;
	$htaccess .= '  RewriteEngine On' . PHP_EOL;
	$htaccess .= '  RewriteRule .*$ ' . get_bloginfo( 'url' ) . '/wp-includes/images/media/default.png [L]' . PHP_EOL;
	$htaccess .= '</FilesMatch>' . PHP_EOL;

	return $htaccess;
}

/**
 * Upload dir
 *
 * @param $param
 *
 * @return mixed
 */
function lana_downloads_manager_upload_dir( $param ) {

	if ( isset( $_POST['type'] ) && 'lana-download' === $_POST['type'] ) {
		if ( empty( $param['subdir'] ) ) {
			$param['path']   = $param['path'] . '/lana-downloads';
			$param['url']    = $param['url'] . '/lana-downloads';
			$param['subdir'] = '/lana-downloads';
		} else {
			$new_subdir = '/lana-downloads' . $param['subdir'];

			$param['path']   = str_replace( $param['subdir'], $new_subdir, $param['path'] );
			$param['url']    = str_replace( $param['subdir'], $new_subdir, $param['url'] );
			$param['subdir'] = str_replace( $param['subdir'], $new_subdir, $param['subdir'] );
		}
	}

	return $param;
}

add_filter( 'upload_dir', 'lana_downloads_manager_upload_dir' );

/**
 * Add Lana Downloads Manager
 * add query vars
 *
 * @param $vars
 *
 * @return array
 */
function lana_downloads_manager_add_query_vars( $vars ) {
	$vars[] = get_option( 'lana_downloads_manager_endpoint', 'download' );
	$vars[] = get_option( 'lana_downloads_manager_post_type_endpoint', 'lana-download' );
	$vars[] = get_option( 'lana_downloads_manager_category_endpoint', 'download-category' );

	return $vars;
}

add_filter( 'query_vars', 'lana_downloads_manager_add_query_vars', 0 );

/**
 * Add Lana Downloads Manager
 * add rewrite endpoint
 */
function lana_downloads_manager_add_rewrite() {
	add_rewrite_endpoint( get_option( 'lana_downloads_manager_endpoint', 'download' ), EP_ALL );
	add_rewrite_endpoint( get_option( 'lana_downloads_manager_post_type_endpoint', 'lana-download' ), EP_ALL );
	add_rewrite_endpoint( get_option( 'lana_downloads_manager_category_endpoint', 'download-category' ), EP_ALL );
	flush_rewrite_rules();
}

add_action( 'init', 'lana_downloads_manager_add_rewrite', 0 );

/**
 * Add Lana Downloads Manager
 * custom wp roles
 */
function lana_downloads_manager_custom_wp_roles() {
	global $wp_roles;

	if ( class_exists( 'WP_Roles' ) && ! isset( $wp_roles ) ) {
		$wp_roles = new WP_Roles();
	}

	if ( is_object( $wp_roles ) ) {
		$wp_roles->add_cap( 'administrator', 'manage_lana_download_logs' );
	}
}

add_action( 'init', 'lana_downloads_manager_custom_wp_roles' );

/**
 * Add Lana Downloads Manager
 * custom post type
 */
function lana_downloads_manager_custom_post_type() {

	/**
	 * Lana Download
	 * default args
	 */
	$lana_download_post_type_args = array(
		'labels'            => array(
			'all_items'          => __( 'All Downloads', 'lana-downloads-manager' ),
			'name'               => __( 'Downloads', 'lana-downloads-manager' ),
			'singular_name'      => __( 'Download', 'lana-downloads-manager' ),
			'add_new'            => __( 'Add New', 'lana-downloads-manager' ),
			'add_new_item'       => __( 'Add Download', 'lana-downloads-manager' ),
			'edit'               => __( 'Edit', 'lana-downloads-manager' ),
			'edit_item'          => __( 'Edit Download', 'lana-downloads-manager' ),
			'new_item'           => __( 'New Download', 'lana-downloads-manager' ),
			'view'               => __( 'View Download', 'lana-downloads-manager' ),
			'view_item'          => __( 'View Download', 'lana-downloads-manager' ),
			'search_items'       => __( 'Search Downloads', 'lana-downloads-manager' ),
			'not_found'          => __( 'No Downloads found', 'lana-downloads-manager' ),
			'not_found_in_trash' => __( 'No Downloads found in trash', 'lana-downloads-manager' ),
			'parent'             => __( 'Parent Download', 'lana-downloads-manager' )
		),
		'description'       => 'Create and manage downloads for your site.',
		'menu_icon'         => 'dashicons-download',
		'show_ui'           => true,
		'capability_type'   => 'post',
		'hierarchical'      => false,
		'supports'          => array(
			'title',
			'editor',
			'thumbnail'
		),
		'show_in_nav_menus' => false,
		'rewrite'           => get_option( 'lana_downloads_manager_post_type_endpoint', 'lana-download' ),
		'query_var'         => get_option( 'lana_downloads_manager_post_type_endpoint', 'lana-download' )
	);

	/**
	 * Lana Download Category
	 * default args
	 */
	$lana_download_category_taxonomy_args = array(
		'hierarchical'      => true,
		'labels'            => array(
			'name'          => __( 'Categories', 'lana-downloads-manager' ),
			'singular_name' => __( 'Category', 'lana-downloads-manager' )
		),
		'show_ui'           => true,
		'show_admin_column' => true,
		'rewrite'           => get_option( 'lana_downloads_manager_category_endpoint', 'download-category' ),
		'query_var'         => get_option( 'lana_downloads_manager_category_endpoint', 'download-category' )
	);

	/**
	 * Lana Download
	 * public args
	 */
	if ( get_option( 'lana_downloads_manager_public', true ) == true ) {

		$public_args = array(
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'has_archive'         => true
		);

		$lana_download_post_type_args         = array_merge( $lana_download_post_type_args, $public_args );
		$lana_download_category_taxonomy_args = array_merge( $lana_download_category_taxonomy_args, $public_args );
	}

	/**
	 * Lana Download
	 * not public args
	 */
	if ( get_option( 'lana_downloads_manager_public', true ) == false ) {

		$non_public_args = array(
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false
		);

		$lana_download_post_type_args         = array_merge( $lana_download_post_type_args, $non_public_args );
		$lana_download_category_taxonomy_args = array_merge( $lana_download_category_taxonomy_args, $non_public_args );
	}

	/**
	 * Lana Download
	 */
	register_post_type( 'lana-download', $lana_download_post_type_args );

	/**
	 * Lana Download Category
	 */
	register_taxonomy( 'lana-download-category', array( 'lana-download' ), $lana_download_category_taxonomy_args );
}

add_action( 'init', 'lana_downloads_manager_custom_post_type' );

/**
 * lana-download post type
 * add columns
 *
 * @param $columns
 *
 * @return array
 */
function lana_downloads_manager_add_lana_download_post_type_columns( $columns ) {
	$column_meta = array(
		'url'       => __( 'URL', 'lana-downloads-manager' ),
		'shortcode' => __( 'Shortcode', 'lana-downloads-manager' ),
		'count'     => __( 'Download Count', 'lana-downloads-manager' )
	);
	$columns     = array_slice( $columns, 0, 2, true ) + $column_meta + array_slice( $columns, 2, null, true );

	return $columns;
}

add_filter( 'manage_lana-download_posts_columns', 'lana_downloads_manager_add_lana_download_post_type_columns' );

/**
 * lana-download post type
 * add data for columns
 *
 * @param $column
 */
function lana_downloads_manager_add_data_lana_download_post_type_columns( $column ) {

	switch ( $column ) {
		case 'url':
			echo '<input type="text" class="lana-download-url" value="' . esc_attr( lana_downloads_manager_get_download_url() ) . '" readonly>';
			break;
		case 'shortcode':
			echo '<input type="text" class="lana-download-shortcode" value="' . esc_attr( lana_downloads_manager_get_download_shortcode() ) . '" readonly>';
			break;
		case 'count':
			echo lana_downloads_manager_get_download_count();
			break;
	}
}

add_action( 'manage_lana-download_posts_custom_column', 'lana_downloads_manager_add_data_lana_download_post_type_columns' );

/**
 * lana-download post type
 * sortable columns
 *
 * @param $columns
 *
 * @return mixed
 */
function lana_downloads_manager_sortable_lana_download_post_type_columns( $columns ) {
	$columns['count'] = 'count';

	return $columns;
}

add_filter( 'manage_edit-lana-download_sortable_columns', 'lana_downloads_manager_sortable_lana_download_post_type_columns' );

/**
 * Lana Downloads Manager
 * download category filter
 */
function lana_downloads_manager_restrict_listings_by_download_category() {
	global $typenow;

	if ( 'lana-download' != $typenow ) {
		return;
	}

	$args = array(
		'show_option_all'  => __( 'All Category', 'lana-downloads-manager' ),
		'show_option_none' => __( 'None', 'lana-downloads-manager' ),
		'name'             => 'lana_download_category',
		'taxonomy'         => 'lana-download-category'
	);

	/** selected */
	if ( isset( $_GET['lana_download_category'] ) ) {
		$args['selected'] = $_GET['lana_download_category'];
	}

	wp_dropdown_categories( $args );
}

add_action( 'restrict_manage_posts', 'lana_downloads_manager_restrict_listings_by_download_category' );

/**
 * Lana Downloads Manager
 * download category filter query
 *
 * @param WP_Query $query
 *
 * @return mixed
 */
function lana_downloads_manager_filter_query_by_download_category( $query ) {
	global $typenow;
	global $pagenow;

	if ( $pagenow == 'edit.php' && $typenow == 'lana-download' && isset( $_GET['lana_download_category'] ) ) {

		$_lana_download_category = intval( $_GET['lana_download_category'] );

		$tax_query = $query->get( 'tax_query' );

		/**
		 * None Category
		 * custom meta query
		 */
		if ( $_lana_download_category == - 1 ) {

			$tax_query[] = array(
				'taxonomy' => 'lana-download-category',
				'operator' => 'NOT EXISTS'
			);

			$query->set( 'tax_query', $tax_query );

			return $query;
		}

		$tax_query[] = array(
			'taxonomy' => 'lana-download-category',
			'field'    => 'term_id',
			'terms'    => $_lana_download_category
		);

		$query->set( 'tax_query', $tax_query );
	}

	return $query;
}

add_filter( 'parse_query', 'lana_downloads_manager_filter_query_by_download_category' );

/**
 * Lana Downloads Manager
 * Load styles
 */
function lana_downloads_manager_styles() {

	wp_register_style( 'lana-downloads-manager', plugin_dir_url( __FILE__ ) . '/assets/css/lana-downloads-manager.css', array(), LANA_DOWNLOADS_MANAGER_VERSION );
	wp_enqueue_style( 'lana-downloads-manager' );
}

add_action( 'wp_enqueue_scripts', 'lana_downloads_manager_styles' );

/**
 * Lana Downloads Manager
 * Load admin styles
 */
function lana_downloads_manager_admin_styles() {

	wp_register_style( 'lana-downloads-manager-admin', plugin_dir_url( __FILE__ ) . '/assets/css/lana-downloads-manager-admin.css', array(), LANA_DOWNLOADS_MANAGER_VERSION );
	wp_enqueue_style( 'lana-downloads-manager-admin' );
}

add_action( 'admin_enqueue_scripts', 'lana_downloads_manager_admin_styles' );

/**
 * Lana Downloads Manager
 * Load admin scripts
 */
function lana_downloads_manager_admin_scripts() {

	/** admin js */
	wp_register_script( 'lana-downloads-manager-admin', plugin_dir_url( __FILE__ ) . '/assets/js/lana-downloads-manager-admin.js', array( 'jquery' ), LANA_DOWNLOADS_MANAGER_VERSION );
	wp_enqueue_script( 'lana-downloads-manager-admin' );
}

add_action( 'admin_enqueue_scripts', 'lana_downloads_manager_admin_scripts' );

/**
 * Lana Downloads Manager
 * add admin menus
 */
function lana_downloads_manager_admin_menu() {
	/** Logs page */
	add_submenu_page( 'edit.php?post_type=lana-download', __( 'Logs', 'lana-downloads-manager' ), __( 'Logs', 'lana-downloads-manager' ), 'manage_lana_download_logs', 'lana-downloads-manager-logs', 'lana_downloads_manager_logs' );

	/** Settings page */
	add_submenu_page( 'edit.php?post_type=lana-download', __( 'Settings', 'lana-downloads-manager' ), __( 'Settings', 'lana-downloads-manager' ), 'manage_options', 'lana-downloads-manager-settings', 'lana_downloads_manager_settings' );

	/** call register settings function */
	add_action( 'admin_init', 'lana_downloads_manager_register_settings' );
}

add_action( 'admin_menu', 'lana_downloads_manager_admin_menu', 12 );

/**
 * Register settings
 */
function lana_downloads_manager_register_settings() {
	register_setting( 'lana-downloads-manager-settings-group', 'lana_downloads_manager_endpoint' );
	register_setting( 'lana-downloads-manager-settings-group', 'lana_downloads_manager_endpoint_type' );
	register_setting( 'lana-downloads-manager-settings-group', 'lana_downloads_manager_post_type_endpoint' );
	register_setting( 'lana-downloads-manager-settings-group', 'lana_downloads_manager_category_endpoint' );
	register_setting( 'lana-downloads-manager-settings-group', 'lana_downloads_manager_public' );
	register_setting( 'lana-downloads-manager-settings-group', 'lana_downloads_manager_logs' );
}

/**
 * Lana Downloads Manager
 * logs page
 */
function lana_downloads_manager_logs() {
	if ( ! get_option( 'lana_downloads_manager_logs', false ) ):
		?>
        <div class="wrap">
            <h2><?php _e( 'Lana Downloads Manager Logs', 'lana-downloads-manager' ); ?></h2>

            <p><?php printf( __( 'Logs is disabled. Go to the <a href="%s">Settings</a> page to enable.', 'lana-downloads-manager' ), admin_url( 'edit.php?post_type=lana-download&page=lana-downloads-manager-settings' ) ); ?></p>
        </div>
		<?php

		return;
	endif;

	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
	require_once( 'includes/class-lana-downloads-manager-logs-list-table.php' );

	$lana_downloads_manager_logs_list_table = new Lana_Downloads_Manager_Logs_List_Table();
	$lana_downloads_manager_logs_list_table->prepare_items();
	?>
    <div class="wrap">
        <h2>
			<?php _e( 'Lana Downloads Manager Logs', 'lana-downloads-manager' ); ?>
            <a href="<?php echo wp_nonce_url( add_query_arg( 'lana_downloads_manager_delete_logs', 'true', admin_url( 'edit.php?post_type=lana-download&page=lana-downloads-manager-logs' ) ), 'delete_logs' ); ?>"
               class="add-new-h2">
				<?php _e( 'Delete Logs', 'lana-downloads-manager' ); ?>
            </a>
        </h2>
        <br/>

        <form id="lana_downloads_manager_logs_form" method="post">
			<?php $lana_downloads_manager_logs_list_table->display(); ?>
        </form>
    </div>
	<?php
}

/**
 * Lana Downloads Manager
 * settings page
 */
function lana_downloads_manager_settings() {
	?>
    <div class="wrap">
        <h2><?php _e( 'Lana Downloads Manager Settings', 'lana-downloads-manager' ); ?></h2>

        <form method="post" action="options.php">
			<?php settings_fields( 'lana-downloads-manager-settings-group' ); ?>

            <h2 class="title"><?php _e( 'General Settings', 'lana-downloads-manager' ); ?></h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="lana_downloads_manager_endpoint">
							<?php _e( 'Endpoint', 'lana-downloads-manager' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="lana_downloads_manager_endpoint" name="lana_downloads_manager_endpoint"
                               value="<?php echo esc_attr( get_option( 'lana_downloads_manager_endpoint', 'download' ) ); ?>">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="lana_downloads_manager_endpoint_type">
							<?php _e( 'Endpoint Type', 'lana-downloads-manager' ); ?>
                        </label>
                    </th>
                    <td>
                        <select name="lana_downloads_manager_endpoint_type" id="lana_downloads_manager_endpoint_type">
                            <option value="ID"
								<?php selected( get_option( 'lana_downloads_manager_endpoint_type', 'ID' ), 'ID' ); ?>>
								<?php _e( 'ID', 'lana-downloads-manager' ); ?>
                            </option>
                            <option value="slug"
								<?php selected( get_option( 'lana_downloads_manager_endpoint_type', 'ID' ), 'slug' ); ?>>
								<?php _e( 'slug', 'lana-downloads-manager' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>

            <h2 class="title"><?php _e( 'Post Type Settings', 'lana-downloads-manager' ); ?></h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="lana_downloads_manager_post_type_endpoint">
							<?php _e( 'Post Type Endpoint', 'lana-downloads-manager' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="lana_downloads_manager_post_type_endpoint"
                               name="lana_downloads_manager_post_type_endpoint"
                               value="<?php echo esc_attr( get_option( 'lana_downloads_manager_post_type_endpoint', 'lana-download' ) ); ?>">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="lana_downloads_manager_category_endpoint">
							<?php _e( 'Category Endpoint', 'lana-downloads-manager' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="lana_downloads_manager_category_endpoint"
                               name="lana_downloads_manager_category_endpoint"
                               value="<?php echo esc_attr( get_option( 'lana_downloads_manager_category_endpoint', 'download-category' ) ); ?>">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="lana_downloads_manager_public">
							<?php _e( 'Public', 'lana-downloads-manager' ); ?>
                        </label>
                    </th>
                    <td>
                        <select name="lana_downloads_manager_public" id="lana_downloads_manager_public">
                            <option value="0"
								<?php selected( get_option( 'lana_downloads_manager_public', true ), false ); ?>>
								<?php _e( 'Disabled', 'lana-downloads-manager' ); ?>
                            </option>
                            <option value="1"
								<?php selected( get_option( 'lana_downloads_manager_public', true ), true ); ?>>
								<?php _e( 'Enabled', 'lana-downloads-manager' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>

            <h2 class="title"><?php _e( 'Log Settings', 'lana-downloads-manager' ); ?></h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="lana_downloads_manager_logs">
							<?php _e( 'Logs', 'lana-downloads-manager' ); ?>
                        </label>
                    </th>
                    <td>
                        <select name="lana_downloads_manager_logs" id="lana_downloads_manager_logs">
                            <option value="0"
								<?php selected( get_option( 'lana_downloads_manager_logs', false ), false ); ?>>
								<?php _e( 'Disabled', 'lana-downloads-manager' ); ?>
                            </option>
                            <option value="1"
								<?php selected( get_option( 'lana_downloads_manager_logs', false ), true ); ?>>
								<?php _e( 'Enabled', 'lana-downloads-manager' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button-primary"
                       value="<?php esc_attr_e( 'Save Changes', 'lana-downloads-manager' ); ?>"/>
            </p>

        </form>
    </div>
	<?php
}

/**
 * Lana Downloads Manager
 * validate endpoint
 *
 * @param $new_value
 * @param $old_value
 *
 * @return mixed
 */
function lana_downloads_manager_validate_endpoint( $new_value, $old_value ) {

	$lana_download = get_post_type_object( 'lana-download' );

	if ( $new_value == $lana_download->rewrite ) {
		return $old_value;
	}

	if ( $new_value == get_option( 'lana_downloads_manager_post_type_endpoint', 'lana-download' ) ) {
		return $old_value;
	}

	return $new_value;
}

/**
 * Lana Downloads Manager
 * validate post type endpoint
 *
 * @param $new_value
 * @param $old_value
 *
 * @return mixed
 */
function lana_downloads_manager_validate_post_type_endpoint( $new_value, $old_value ) {

	if ( $new_value == get_option( 'lana_downloads_manager_endpoint', 'lana-download' ) ) {
		return $old_value;
	}

	return $new_value;
}

/**
 * Lana Downloads Manager
 * validate endpoints
 */
function lana_downloads_manager_validate_endpoints() {
	add_filter( 'pre_update_option_lana_downloads_manager_endpoint', 'lana_downloads_manager_validate_endpoint', 10, 2 );
	add_filter( 'pre_update_option_lana_downloads_manager_post_type_endpoint', 'lana_downloads_manager_validate_post_type_endpoint', 10, 2 );
}

add_action( 'init', 'lana_downloads_manager_validate_endpoints' );

/**
 * Add Lana Downloads Manager metaboxes
 * - File Manager to normal
 * - Download Information to side
 */
function lana_downloads_manager_file_meta_box() {
	add_meta_box( 'lana-downloads-manager', 'File Manager', 'lana_downloads_manager_meta_box_render', 'lana-download', 'normal', 'core' );
	add_meta_box( 'lana-downloads-manager-info', 'Download Information', 'lana_downloads_manager_info_meta_box_render', 'lana-download', 'side', 'core' );
}

add_action( 'add_meta_boxes', 'lana_downloads_manager_file_meta_box' );

/**
 * File Manager
 * metabox
 *
 * @param $post
 */
function lana_downloads_manager_meta_box_render( $post ) {
	include_once 'views/lana-downloads-manager-metabox.php';
}

/**
 * Download Information
 * metabox
 *
 * @param $post
 */
function lana_downloads_manager_info_meta_box_render( $post ) {
	include_once 'views/lana-downloads-manager-info-metabox.php';
}

/**
 * Lana Downloads Manager
 * Request Handler
 */
function lana_downloads_manager_download_handler() {
	global $wp;

	$endpoint      = get_option( 'lana_downloads_manager_endpoint', 'download' );
	$endpoint_type = get_option( 'lana_downloads_manager_endpoint_type', 'ID' );

	if ( ! empty( $_GET[ $endpoint ] ) ) {
		$wp->query_vars[ $endpoint ] = $_GET[ $endpoint ];
	}

	if ( ! empty( $wp->query_vars[ $endpoint ] ) ) {

		define( 'DONOTCACHEPAGE', true );

		$download_id = sanitize_title( stripslashes( $wp->query_vars[ $endpoint ] ) );

		if ( $endpoint_type == 'ID' ) {
			$download_id = absint( $download_id );
		}

		if ( $endpoint_type == 'slug' ) {
			$page = get_page_by_path( $download_id, OBJECT, 'lana-download' );

			if ( $page ) {
				$download_id = $page->ID;
			}
		}

		if ( empty( $download_id ) ) {
			wp_die( __( 'No download_id defined.', 'lana-downloads-manager' ) );
		}

		$file_url = get_post_meta( $download_id, '_lana_download_file_url', true );

		if ( empty( $file_url ) ) {
			wp_die( __( 'No file URL defined.', 'lana-downloads-manager' ) . ' <a href="' . home_url() . '">' . __( 'Go to homepage &rarr;', 'lana-downloads-manager' ) . '</a>', __( 'Download Error', 'lana-downloads-manager' ) );
		}

		list( $file_path, $remote_file, $local_file ) = lana_downloads_manager_parse_file_path( $file_url );

		if ( empty( $file_path ) ) {
			wp_die( __( 'No file path defined.', 'lana-downloads-manager' ) . ' <a href="' . home_url() . '">' . __( 'Go to homepage &rarr;', 'lana-downloads-manager' ) . '</a>', __( 'Download Error', 'lana-downloads-manager' ) );
		}

		$increment_download_count = false;

		/**
		 * Check Cookie
		 */
		if ( false == lana_downloads_manager_cookie_exists( $download_id ) ) {
			$increment_download_count = true;
			lana_downloads_manager_set_cookie( $download_id );
		}

		/**
		 * Check Log
		 */
		if ( lana_downloads_manager_get_log_user_ip_has_downloaded( $download_id ) ) {
			$increment_download_count = false;
		}

		/**
		 * Output configs
		 */
		if ( ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 0 );
		}

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 );
		}

		@session_write_close();

		if ( ini_get( 'zlib.output_compression' ) ) {
			@ini_set( 'zlib.output_compression', 'Off' );
		}

		@error_reporting( 0 );
		@ob_end_clean();

		while( ob_get_level() > 0 ){
			@ob_end_clean();
		}

		/**
		 * Local file
		 */
		if ( $file_path && $local_file ) {
			lana_downloads_manager_add_log( $download_id );
			lana_downloads_manager_add_download_count( $download_id, $increment_download_count );

			$filename = sprintf( '"%s"', addcslashes( basename( $file_path ), '"\\' ) );

			if ( strstr( $filename, '?' ) ) {
				$filename = current( explode( '?', $filename ) );
			}

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Content-Length:' . filesize( $file_path ) );
			header( 'Connection: Keep-Alive' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			readfile( $file_path );
			exit;
		}

		/**
		 * Remote file
		 */
		if ( $file_path && $remote_file ) {
			lana_downloads_manager_add_log( $download_id );
			lana_downloads_manager_add_download_count( $download_id, $increment_download_count );

			$filename = sprintf( '"%s"', addcslashes( basename( $file_path ), '"\\' ) );

			if ( strstr( $filename, '?' ) ) {
				$filename = current( explode( '?', $filename ) );
			}

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Connection: Keep-Alive' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			readfile( $file_path );
			exit;
		}
	}
}

add_action( 'parse_request', 'lana_downloads_manager_download_handler', 0 );

/**
 * Parse file path
 *
 * @param $file_path
 *
 * @return array
 */
function lana_downloads_manager_parse_file_path( $file_path ) {

	$remote_file      = true;
	$parsed_file_path = parse_url( $file_path );

	$wp_uploads     = wp_upload_dir();
	$wp_uploads_dir = $wp_uploads['basedir'];
	$wp_uploads_url = $wp_uploads['baseurl'];

	if ( ( ! isset( $parsed_file_path['scheme'] ) || ! in_array( $parsed_file_path['scheme'], array(
				'http',
				'https',
				'ftp'
			) ) ) && isset( $parsed_file_path['path'] ) && file_exists( $parsed_file_path['path'] )
	) {

		/** This is an absolute path */
		$remote_file = false;

	} elseif ( strpos( $file_path, $wp_uploads_url ) !== false ) {

		/** This is a local file given by URL so we need to figure out the path */
		$remote_file = false;
		$file_path   = trim( str_replace( $wp_uploads_url, $wp_uploads_dir, $file_path ) );
		$file_path   = realpath( $file_path );

	} elseif ( is_multisite() && ( ( strpos( $file_path, network_site_url( '/', 'http' ) ) !== false ) || ( strpos( $file_path, network_site_url( '/', 'https' ) ) !== false ) ) ) {

		/** This is a local file outside of wp-content so figure out the path */
		$remote_file = false;
		$file_path   = str_replace( network_site_url( '/', 'https' ), ABSPATH, $file_path );
		$file_path   = str_replace( network_site_url( '/', 'http' ), ABSPATH, $file_path );
		$file_path   = str_replace( $wp_uploads_url, $wp_uploads_dir, $file_path );
		$file_path   = realpath( $file_path );

	} elseif ( strpos( $file_path, site_url( '/', 'http' ) ) !== false || strpos( $file_path, site_url( '/', 'https' ) ) !== false ) {

		/** This is a local file outside of wp-content so figure out the path */
		$remote_file = false;
		$file_path   = str_replace( site_url( '/', 'https' ), ABSPATH, $file_path );
		$file_path   = str_replace( site_url( '/', 'http' ), ABSPATH, $file_path );
		$file_path   = realpath( $file_path );

	} elseif ( file_exists( ABSPATH . $file_path ) ) {

		/** Path needs an abspath to work */
		$remote_file = false;
		$file_path   = ABSPATH . $file_path;
		$file_path   = realpath( $file_path );
	}

	$local_file = $remote_file == false;

	return array( $file_path, $remote_file, $local_file );
}

/**
 * Lana Downloads Manager
 * Cookie exists?
 *
 * @param $download_id
 *
 * @return bool
 */
function lana_downloads_manager_cookie_exists( $download_id ) {
	$exists = false;
	$cdata  = lana_downloads_manager_get_cookie();

	if ( ! empty( $cdata ) ) {
		if ( $cdata['download_id'] == $download_id ) {
			$exists = true;
		}
	}

	return $exists;
}

/**
 * Lana Downloads Manager
 * Get Cookie
 * @return array|mixed|null|object
 */
function lana_downloads_manager_get_cookie() {
	$cdata = null;

	if ( ! empty( $_COOKIE['lana_downloads_manager'] ) ) {
		$cdata = json_decode( base64_decode( $_COOKIE['lana_downloads_manager'] ), true );
	}

	return $cdata;
}

/**
 * Lana Downloads Manager
 * Set Cookie
 *
 * @param $download_id
 */
function lana_downloads_manager_set_cookie( $download_id ) {
	setcookie( 'lana_downloads_manager', base64_encode( json_encode( array(
		'download_id' => $download_id
	) ) ), time() + 3600, COOKIEPATH, COOKIE_DOMAIN, false, true );
}

/**
 * Lana Downloads Manager
 * User IP has downloaded (in last hours)
 *
 * @param $download_id
 *
 * @return bool
 */
function lana_downloads_manager_get_log_user_ip_has_downloaded( $download_id ) {
	global $wpdb;

	$table_name    = $wpdb->prefix . 'lana_downloads_manager_logs';
	$user_ip       = sanitize_text_field( ! empty( $_SERVER['HTTP_X_FORWARD_FOR'] ) ? $_SERVER['HTTP_X_FORWARD_FOR'] : $_SERVER['REMOTE_ADDR'] );
	$download_date = date( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );

	return ( absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . $table_name . " WHERE download_id = '%d' AND user_ip = '%s' AND download_date > '%s'", $download_id, $user_ip, $download_date ) ) ) > 0 );
}

/**
 * Lana Downloads Manager
 * add log to database
 *
 * @param $download_id
 */
function lana_downloads_manager_add_log( $download_id ) {
	global $wpdb;

	if ( get_option( 'lana_downloads_manager_logs', false ) ) {

		$wpdb->hide_errors();

		$wpdb->insert( $wpdb->prefix . 'lana_downloads_manager_logs', array(
			'user_id'       => absint( get_current_user_id() ) > 0 ? absint( get_current_user_id() ) : null,
			'user_ip'       => sanitize_text_field( ! empty( $_SERVER['HTTP_X_FORWARD_FOR'] ) ? $_SERVER['HTTP_X_FORWARD_FOR'] : $_SERVER['REMOTE_ADDR'] ),
			'user_agent'    => sanitize_text_field( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' ),
			'download_id'   => absint( $download_id ),
			'download_date' => current_time( 'mysql' )
		), array( '%s', '%s', '%s', '%d', '%s' ) );
	}
}

/**
 * Lana Downloads Manager
 * delete logs from database
 */
function lana_downloads_manager_delete_logs() {
	global $wpdb;

	if ( empty( $_GET['lana_downloads_manager_delete_logs'] ) ) {
		return;
	}

	check_admin_referer( 'delete_logs' );

	$table_name = $wpdb->prefix . 'lana_downloads_manager_logs';
	$wpdb->query( "TRUNCATE TABLE " . $table_name . ";" );
}

add_action( 'admin_init', 'lana_downloads_manager_delete_logs' );

/**
 * Add Download Count
 *
 * @param $download_id
 * @param $increment_download_count
 */
function lana_downloads_manager_add_download_count( $download_id, $increment_download_count = true ) {
	if ( $increment_download_count ) {
		update_post_meta( $download_id, '_lana_download_count', absint( get_post_meta( $download_id, '_lana_download_count', true ) ) + 1 );
	}
}

/**
 * Get Download Count
 *
 * @param string $download_id
 *
 * @return int|mixed
 */
function lana_downloads_manager_get_download_count( $download_id = '' ) {
	global $post;

	$abs_download_id = absint( $download_id );

	if ( ! empty( $download_id ) && ! empty( $abs_download_id ) && is_numeric( $download_id ) ) {
		$post = get_post( absint( $download_id ) );
	}

	$lana_download_count = get_post_meta( $post->ID, '_lana_download_count', true );

	if ( $lana_download_count ) {
		return $lana_download_count;
	}

	return 0;
}

/**
 * Get Download URL
 *
 * @param string $download_id
 *
 * @return mixed
 */
function lana_downloads_manager_get_download_url( $download_id = '' ) {
	global $post;

	$abs_download_id = absint( $download_id );

	if ( ! empty( $download_id ) && ! empty( $abs_download_id ) && is_numeric( $download_id ) ) {
		$post = get_post( absint( $download_id ) );
	}

	$scheme        = parse_url( get_option( 'home' ), PHP_URL_SCHEME );
	$endpoint      = get_option( 'lana_downloads_manager_endpoint', 'download' );
	$endpoint_type = get_option( 'lana_downloads_manager_endpoint_type', 'ID' );
	$value         = $post->ID;

	if ( $endpoint_type == 'ID' ) {
		$value = $post->ID;
	}

	if ( $endpoint_type == 'slug' ) {
		$value = $post->post_name;
	}

	if ( get_option( 'permalink_structure' ) ) {
		$link = home_url( '/' . $endpoint . '/' . $value . '/', $scheme );
	} else {
		$link = add_query_arg( $endpoint, $value, home_url( '', $scheme ) );
	}

	return apply_filters( 'lana_downloads_manager_get_download_url', esc_url_raw( $link ) );
}

/**
 * Get Download shortcode
 *
 * @param string $download_id
 *
 * @return string
 */
function lana_downloads_manager_get_download_shortcode( $download_id = '' ) {
	global $post;

	$abs_download_id = absint( $download_id );

	if ( ! empty( $download_id ) && ! empty( $abs_download_id ) && is_numeric( $download_id ) ) {
		$post = get_post( absint( $download_id ) );
	}

	$endpoint_type = get_option( 'lana_downloads_manager_endpoint_type', 'ID' );

	if ( $endpoint_type == 'ID' ) {
		return '[lana_download id="' . esc_attr( $post->ID ) . '"]';
	}

	if ( $endpoint_type == 'slug' ) {
		return '[lana_download file="' . esc_attr( $post->post_name ) . '"]';
	}

	return '[lana_download id="' . esc_attr( $post->ID ) . '"]';
}

/**
 * Lana Download Shortcode
 * with Bootstrap
 *
 * @param $atts
 *
 * @return string
 */
function lana_download_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'id'   => '',
		'file' => '',
		'text' => __( 'Download', 'lana-downloads-manager' )
	), $atts );

	if ( ! empty( $a['id'] ) ) {
		$lana_download = get_post( $a['id'] );
	}

	if ( ! empty( $a['file'] ) ) {
		$lana_download = get_page_by_path( $a['file'], OBJECT, 'lana-download' );
	}

	if ( isset( $lana_download ) && $lana_download ) {

		$output = '<div class="lana-download-shortcode">';

		/** download button */
		$output .= '<p>';
		$output .= '<a class="btn btn-primary lana-download" href="' . esc_attr( lana_downloads_manager_get_download_url( $lana_download->ID ) ) . '" role="button">';
		$output .= esc_html( $a['text'] ) . ' ';

		/** counter */
		$output .= '<span class="badge">';
		$output .= lana_downloads_manager_get_download_count( $lana_download->ID );
		$output .= '</span>';

		$output .= '</a>';
		$output .= '</p>';

		$output .= '</div>';

		return $output;
	}

	return '';
}

add_shortcode( 'lana_download', 'lana_download_shortcode' );

/**
 * TinyMCE
 * Register Plugins
 *
 * @param $plugins
 *
 * @return mixed
 */
function lana_downloads_manager_add_mce_plugin( $plugins ) {

	$plugins['lana_download'] = plugin_dir_url( __FILE__ ) . '/assets/js/download-shortcode.js';

	return $plugins;
}

/**
 * TinyMCE
 * Register Buttons
 *
 * @param $buttons
 *
 * @return mixed
 */
function lana_downloads_manager_add_mce_button( $buttons ) {

	array_push( $buttons, 'lana_download' );

	return $buttons;
}

/**
 * TinyMCE
 * Add Custom Buttons
 */
function lana_downloads_manager_add_mce_shortcodes_buttons() {
	add_filter( 'mce_external_plugins', 'lana_downloads_manager_add_mce_plugin' );
	add_filter( 'mce_buttons_3', 'lana_downloads_manager_add_mce_button' );
}

add_action( 'init', 'lana_downloads_manager_add_mce_shortcodes_buttons' );

/**
 * Lana Downloads Manager - ajax
 * get lana download list
 */
function lana_downloads_manager_ajax_get_lana_download_list() {

	$lana_download = array();

	$args     = array(
		'post_type'      => 'lana-download',
		'post_status'    => 'publish',
		'posts_per_page' => - 1
	);
	$my_query = new WP_Query( $args );

	while( $my_query->have_posts() ){
		$my_query->the_post();

		$lana_download[ get_the_ID() ] = get_the_title();
	}

	wp_reset_postdata();

	echo json_encode( $lana_download );

	wp_die();
}

add_action( 'wp_ajax_lana_downloads_manager_get_lana_download_list', 'lana_downloads_manager_ajax_get_lana_download_list' );

/**
 * Lana Downloads Manager
 * save post
 *
 * @param $post_id
 */
function lana_downloads_manager_save_post( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	/**
	 * User can't edit
	 * this post
	 */
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	/**
	 * in Lana Downloads Manager
	 * initialized nonce field
	 */
	if ( empty( $_POST['lana_downloads_manager_nonce_field'] ) ) {
		return;
	}

	/**
	 * in Lana Downloads Manager
	 * initialized nonce field
	 */
	if ( ! wp_verify_nonce( $_POST['lana_downloads_manager_nonce_field'], 'save' ) ) {
		return;
	}

	update_post_meta( $post_id, '_lana_download_file_url', sanitize_text_field( $_POST['lana_download_file_url'] ) );
	update_post_meta( $post_id, '_lana_download_file_id', absint( $_POST['lana_download_file_id'] ) );
}

add_action( 'save_post', 'lana_downloads_manager_save_post' );