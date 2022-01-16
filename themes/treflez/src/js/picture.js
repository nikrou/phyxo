import './jquery-mobile-events'
import 'jquery.cookie'

$(function () {
  if ($('#theImage').length) {
    $('#theImage img').on('swipeleft swiperight', function (event) {
      if (event.type == 'swipeleft') {
        $('#navigationButtons a#navNextPicture i').trigger('click')
      } else if (event.type == 'swiperight') {
        $('#navigationButtons a#navPrevPicture i').trigger('click')
      } else {
        return
      }
    })
  }

  function changeImgSrc(url, typeSave, typeMap) {
    const mainImage = document.getElementById('theMainImage')
    if (mainImage) {
      mainImage.removeAttribute('width')
      mainImage.removeAttribute('height')
      mainImage.src = url
      mainImage.useMap = '#map' + typeMap
    }
    $('.derivative-li').removeClass('active')
    $('#derivative' + typeMap).addClass('active')

    $.cookie('picture_deriv', typeSave)
  }

  $('[data-action="changeImgSrc"]').on('click', function (e) {
    changeImgSrc(
      $(this).data('url'),
      $(this).data('type-save'),
      $(this).data('type-map')
    )
  })

  if ($('.comments-list').length) {
    $('.comment [data-role="delete-comment"]').on('click', function () {
      return confirm(confirm_message)
    })
  }
})
