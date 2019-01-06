var queuedManager = $.manageAjax.create('queued', {
    queue: true,
    maxRequests: 1
});

var nb_plugins = jQuery('.plugin.active').size();
var done = 0;

$(function() {
    /* group action */
    $('#deactivate-all').click(function() {
        if (confirm(confirmMsg)) {
            $('.plugin.active').each(function() {
                performPluginDeactivate($(this).attr('id'));
            });
        }
    });

    function performPluginDeactivate(id) {
        queuedManager.add({
            type: 'GET',
            dataType: 'json',
            url: '../ws.php',
            data: { method: 'pwg.plugins.performAction', action: 'deactivate', plugin: id, pwg_token: pwg_token },
            success: function(data) {
                if (data['stat'] == 'ok') {
                    $('#' + id)
                        .removeClass('active')
                        .addClass('inactive');
                }
                done++;

                if (done === nb_plugins) {
                    location.reload();
                }
            }
        });
    }

    /* incompatible plugins */
    $.ajax({
        method: 'GET',
        url: './index.php',
        data: { page: 'plugins', section: 'installed', incompatible_plugins: true },
        dataType: 'json',
        success: function(data) {
            for (i = 0; i < data.length; i++) {
                if (show_details) {
                    $('#' + data[i] + ' .plugin-name').prepend('<a class="warning" title="' + incompatible_msg + '"></a>');
                } else {
                    $('#' + data[i] + ' .plugin-name').prepend('<span class="warning" title="' + incompatible_msg + '"></span>');
                }
                $('#' + data[i]).addClass('incompatible');
                $('#' + data[i] + ' .activate').attr('onClick', 'return confirm(incompatible_msg + activate_msg);');
            }
        }
    });
});
