tinymce.PluginManager.add('lana_download', function (editor, url) {
    editor.addButton('lana_download', {
        tooltip: 'Download Shortcode',
        icon: 'lana-download',
        onclick: function () {

            jQuery.post(ajaxurl, {
                action: 'lana_downloads_manager_get_lana_download_list'
            }, function (response) {

                tinymce.activeEditor.windowManager.open({
                    title: 'Download',
                    url: url + '/../html/download.html',
                    width: 480,
                    height: 140
                }, {
                    lana_download_list: response
                });
            });
        }
    });
});