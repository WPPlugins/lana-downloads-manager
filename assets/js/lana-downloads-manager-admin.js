jQuery(function () {

    var wp_media_lana_downloads_manager;

    jQuery('.lana-downloads-manager').on('click', '.upload-file-button', function (event) {

        var lana_downloads_manager = jQuery('.lana-downloads-manager');
        var upload_file_button = jQuery(this);
        var upload_file_url = lana_downloads_manager.find('.upload-file-url');
        var upload_file_id = lana_downloads_manager.find('.upload-file-id');

        event.preventDefault();

        if (wp_media_lana_downloads_manager) {
            wp_media_lana_downloads_manager.close();
        }

        wp_media_lana_downloads_manager = wp.media.frames.lana_downloads_manager = wp.media({
            title: upload_file_button.data('dialog-title'),
            library: {
                type: ''
            },
            button: {
                text: upload_file_button.data('dialog-button')
            },
            multiple: false,
            states: [
                new wp.media.controller.Library({
                    library: wp.media.query(),
                    multiple: false,
                    title: upload_file_button.data('dialog-title')
                })
            ]
        });

        wp_media_lana_downloads_manager.on('open', function () {
            var attachment = wp.media.attachment(upload_file_id.val());
            attachment.fetch();

            var selection = wp_media_lana_downloads_manager.state().get('selection');
            selection.add(attachment ? [attachment] : []);
        });

        wp_media_lana_downloads_manager.on('select', function () {

            var attachment = wp_media_lana_downloads_manager.state().get('selection').first().toJSON();

            upload_file_url.val(attachment.url);
            upload_file_id.val(attachment.id);
        });

        wp_media_lana_downloads_manager.on('ready', function () {
            wp_media_lana_downloads_manager.uploader.options.uploader.params = {
                type: 'lana-download'
            };
        });

        wp_media_lana_downloads_manager.open();
    });

});