import 'selectize';

$.flashMessage = function(source, data) {
    $(source).html('');
    $(source)
        .hide()
        .append(data)
        .fadeIn(500); //.fadeOut(3000);
};

$.fn.flashMessage = function(data) {
    this.each(function() {
        new $.flashMessage(this, data);
    });
};

function changeSubmitStatus(status) {
    if (status == 'disabled') {
        $('#user-tags-update')
            .prop('disabled', true)
            .addClass('disabled')
            .css('cursor', 'default');
    } else {
        $('#user-tags-update')
            .prop('disabled', false)
            .removeClass('disabled')
            .css('cursor', 'pointer');
    }
}

function formResponse(data, textStatus) {
    let message = '';
    changeSubmitStatus('disabled');

    message += '<div class="alert-dissmissible';

    if (data.stat == 'ok') {
        message += ' alert alert-info">' + user_tags.tags_updated;
    } else {
        message += 'alert alert-error">' + data.message;
    }
    message += '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
    message += '<span aria-hidden="true">&times;</span>';
    message += '</button>';
    message += '</div>';

    if (message !== '') {
        $('.flash-messages').flashMessage(message);
    }
}

$(function() {
    if ($('#user-tags').length == 0) {
        return;
    }

    $.user_tags_target = $('#user-tags');
    $.user_tags_target.after('<div class="flash-messages"></div>');
    changeSubmitStatus('disabled');

    $('.edit-tags').click(function() {
        $.user_tags_target.parent().toggleClass('js-hidden');
    });

    var selectize_options = {
        delimiter: ',',
        persist: false,
        valueField: 'id',
        labelField: 'name',
        searchField: 'name',
        load: function(query, callback) {
            if (!query.length) {
                return callback;
            }
            $.getJSON(user_tags.ws_getList, 'q=' + query, function(data) {
                for (var i = 0; i < data.result.tags.length; i++) {
                    data.result.tags[i]['id'] = '~~' + data.result.tags[i]['id'] + '~~';
                }
                callback(data.result.tags);
            });
        },
        onChange: function() {
            changeSubmitStatus('enabled');
        }
    };

    if (user_tags.allow_delete) {
        selectize_options.plugins = ['remove_button'];
    }
    if (user_tags.allow_creation) {
        selectize_options.create = true;
    }
    $.user_tags_target.selectize(selectize_options);
});

$('#user-tags-form').submit(function(e) {
    var serialized_form = $(this).serialize();
    serialized_form = serialized_form.replace(/user_tags/g, 'tags');

    $.post(
        $(this).attr('action'),
        serialized_form,
        function(data, textStatus) {
            formResponse(data, textStatus);
        },
        'json'
    ).fail(function(data, textStatus) {
        formResponse(data.responseJSON, textStatus);
    });
    e.preventDefault();
});
