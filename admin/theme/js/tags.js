$(function() {
    function displayDeletionWarnings() {
        $('.warningDeletion').show();
        $('input[name=destination_tag]:checked')
            .parent('label')
            .children('.warningDeletion')
            .hide();
    }

    displayDeletionWarnings();

    $('#mergeTags label').click(function() {
        displayDeletionWarnings();
    });

    $('input[name=merge]').click(function() {
        if ($('ul.tagSelection input[type=checkbox]:checked').length < 2) {
            alert(phyxo_msg.select_at_least_two_tags);
            return false;
        }
    });

    $('#searchInput').on('keydown', function(e) {
        var $this = $(this),
            timer = $this.data('timer');

        if (timer) {
            clearTimeout(timer);
        }

        $this.data(
            'timer',
            setTimeout(function() {
                var val = $this.val();
                if (!val) {
                    $('.tagSelection>li').show();
                    $('#filterIcon').css('visibility', 'hidden');
                } else {
                    $('#filterIcon').css('visibility', 'visible');
                    var regex = new RegExp(val.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&'), 'i');
                    $('.tagSelection>li').each(function() {
                        var $li = $(this),
                            text = $.trim($('label', $li).text());
                        $li.toggle(regex.test(text));
                    });
                }
            }, 300)
        );

        if (e.keyCode == 13) {
            // Enter
            e.preventDefault();
        }
    });

    $('.tagSelection').on('click', 'label', function() {
        var parent = $(this).parent('li');
        var checkbox = $(this).children('input[type=checkbox]');

        if ($(checkbox).is(':checked')) {
            parent.addClass('tagSelected');
        } else {
            parent.removeClass('tagSelected');
        }
    });
});
