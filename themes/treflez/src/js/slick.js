import 'slick-carousel';

$(function () {
    if ($('#thumbnailCarousel').length) {
        $('#thumbnailCarousel').slick(slick_params);

        const currentThumbnailIndex = $('#thumbnail-active').data('index');
        $('#thumbnailCarousel').slick('goTo', currentThumbnailIndex, true);
    }
});
