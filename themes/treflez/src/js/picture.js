import './jquery-mobile-events';

$(function() {
    if ($('#theImage').length) {
        $('#theImage img').bind('swipeleft swiperight', function(event) {
            if (event.type == 'swipeleft') {
                $('#navigationButtons a#navNextPicture i').click();
            } else if (event.type == 'swiperight') {
                $('#navigationButtons a#navPrevPicture i').click();
            } else {
                return;
            }
        });
    }

    function changeImgSrc(url, typeSave, typeMap) {
        const mainImage = document.getElementById('theMainImage');
        if (mainImage) {
            mainImage.removeAttribute('width');
            mainImage.removeAttribute('height');
            mainImage.src = url;
            mainImage.useMap = '#map' + typeMap;
        }
        $('.derivative-li').removeClass('active');
        $('#derivative' + typeMap).addClass('active');
        document.cookie = 'picture_deriv=' + typeSave + ';path=' + phyxo_cookie_path;
    }

    $('[data-action="changeImgSrc"]').click(function(e) {
        changeImgSrc($(this).data('url'), $(this).data('type-save'), $(this).data('type-map'));
    });
});
