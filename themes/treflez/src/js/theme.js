$(function() {
    // Grid view button click
    $('#btn-grid').click(function() {
        if ($(this).hasClass('active')) {
            return;
        }
        $.cookie('view', 'grid');
        $('#btn-grid').addClass('active');
        $('#btn-list').removeClass('active');
        $('#content')
            .removeClass('content-list')
            .addClass('content-grid')
            .find('.col-outer').each(function() {
                $(this).find('.card-body').attr('style', '');
                $(this).find('a').addClass('d-block').find('.card-img-left').addClass('card-img-top').removeClass('card-img-left');
                $(this).find('.card-body.list-view-only').addClass('d-none');
                $(this).find('.addCollection').removeAttr('style');
                $(this).removeClass('col-12')
                       .addClass($(this).data('grid-classes'))
                       .one(
                           'webkitTransitionEnd',
                           function () {
                               $('#content').find('.card-body').removeAttr('style').equalHeights();
                           })
            });
    });

    // List view button click
    $('#btn-list').click(function() {
        if ($(this).hasClass('active')) {
            return;
        }
        $.cookie('view', 'list');
        $('#btn-list').addClass('active');
        $('#btn-grid').removeClass('active');
        $('#content')
            .removeClass('content-grid')
            .addClass('content-list')
            .height('auto')
            .find('.col-outer').each(function() {
                $(this).find('a').removeClass('d-block').find('.card-img-top').addClass('card-img-left').removeClass('card-img-top');
                $(this).find('.card-body.list-view-only').removeClass('d-none');
                $(this).find('.addCollection').attr('style', 'width: ' + $(this).find('img').width() + 'px');
                $(this).removeClass($(this).data('grid-classes'))
                       .addClass('col-12')
                       .one(
			   'webkitTransitionEnd',
			   function () {
                               $('#content').find('.card-body').removeAttr('style').equalHeights();
			   })
            });
    });

    // Side bar
    var sidebar = $("#sidebar");
    var navigationButtons = $('#navigationButtons')
    if (sidebar.length && navigationButtons.length) {
        sidebar.css('top', (navigationButtons.offset().top + 1) + 'px');
        $('#info-link').click(function () {
            var sidebar = $('#sidebar');
            if (parseInt(sidebar.css('right')) < 0) {
                sidebar.animate({right: "+=250"}, 500);
            } else {
                sidebar.animate({right: "-=250"}, 500);
            }
            return false;
        });
    }
});
