import 'slick-carousel';

$(function() {
    if ($('#thumbnailCarousel').length) {
        $('#thumbnailCarousel').slick(slick_params);
        const currentThumbnailIndex = $('#thumbnailCarousel .thumbnail-active:not(.slick-cloned)').data('slick-index');
        $('#thumbnailCarousel').slick('goTo', currentThumbnailIndex, true);
    }
});
