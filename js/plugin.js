(function ($) {
    tinymce.create(
        'tinymce.plugins.onwp_plugin',
        {
            init: function (editor, url) {
                editor.addButton(
                    'onwp_plugin',
                    {
                        image: url + '/../images/on_logo.png',
                        title: 'Select a page from OneNote',
                        onclick: function () {
                            var left = (screen.width / 2) - 300;
                            var top = (screen.height / 2) - 260;
                            window.open(
                                onwpPopupUrl,
                                'onwp-plugin',
                                'width=600,height=520,toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=no,copyhistory=no,left=' + left + ',top=' + top
                            );
                        }
                    }
                );
            }
        }
    );

    tinymce.PluginManager.add('onwp_plugin', tinymce.plugins.onwp_plugin);

    window._ONWPPluginImportDone = function (pageData) {
        document.getElementById('title').value = pageData.title;
        tinymce.activeEditor.setContent(pageData.html);
        tinymce.activeEditor.isNotDirty = false;
    };
})(jQuery);