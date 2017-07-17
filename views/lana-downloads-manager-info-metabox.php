<div class="lana-downloads-manager-info">
    <label>
		<?php _e( 'ID:', 'lana-downloads-manager' ); ?>
    </label>
	<?php echo $post->ID; ?>
    <hr/>
    <label>
		<?php _e( 'Download count:', 'lana-downloads-manager' ); ?>
    </label>
	<?php echo lana_downloads_manager_get_download_count(); ?>
    <hr/>
    <label for="lana_download_url">
		<?php _e( 'URL:', 'lana-downloads-manager' ); ?>
    </label>
    <input type="text" id="lana_download_url"
           value="<?php echo esc_attr( lana_downloads_manager_get_download_url() ); ?>" readonly>
    <hr/>
    <label for="lana_download_shortcode">
		<?php _e( 'Shortcode:', 'lana-downloads-manager' ); ?>
    </label>
    <input type="text" id="lana_download_shortcode"
           value="<?php echo esc_attr( lana_downloads_manager_get_download_shortcode() ); ?>" readonly>
</div>