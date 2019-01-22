import '../scss/theme.scss';

import 'bootstrap';
import './theme';
import './keyboard-navigation';
import './tags';
import './thumbnails-loader';
import './picture';
import './picture-tags';
import './search';
import './ws';
import './slick';
import './photoswipe';

$(function() {
    $('#categoriesDropdownMenu').on('show.bs.dropdown', function() {
        $(this)
            .find('a.dropdown-item')
            .each(function() {
                var level = $(this).data('level');
                var padding = parseInt($(this).css('padding-left'));
                if (level > 0) {
                    $(this).css('padding-left', padding + 10 * level + 'px');
                }
            });
    });

    var qsearch_icon = $('#navbar-menubar > #quicksearch > .fa-search');
    var qsearch_text = $('#navbar-menubar > #quicksearch #qsearchInput');
    $(qsearch_icon).click(function() {
        $(qsearch_text).focus();
    });
    $('#navbar-menubar > #quicksearch').css({ color: $('#navbar-menubar .nav-link').css('color') });

    $('.navbar-main .navbar-collapse').on('show.bs.collapse', function() {
        $('.navbar-main').attr('style', 'background-color: rgba(0, 0, 0, 0.9) !important');
    });
    $('.navbar-main .navbar-collapse').on('hidden.bs.collapse', function() {
        $('.navbar-main').attr('style', '');
    });

    // move to main navbar to avoid scrolling issues in navmenu on mobile devices
    $('#languageSwitch').appendTo('#navbar-menubar > ul.navbar-nav');

    $('#show_exif_data').on('click', function() {
        if ($('#full_exif_data').hasClass('d-none')) {
            $('#full_exif_data')
                .addClass('d-flex')
                .removeClass('d-none');
            $('#show_exif_data').html('<i class="fas fa-info mr-1"></i> ' + phyxo_hide_exif_data);
        } else {
            $('#full_exif_data')
                .addClass('d-none')
                .removeClass('d-flex');
            $('#show_exif_data').html('<i class="fas fa-info mr-1"></i> ' + phyxo_show_exif_data);
        }
    });
});
