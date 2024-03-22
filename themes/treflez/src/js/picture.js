import './jquery-mobile-events'

function setCookie(name, value, days = 365) {
  const date = new Date()
  date.setDate(date.getDate() + days)
  document.cookie = `${name}=${encodeURIComponent(
    value
  )}; expires=${date.toUTCString()}; path=/`
}

$(function () {
  if ($('#mainImage').length) {
    $('#mainImage img').on('swipeleft swiperight', function (event) {
      if (event.type == 'swipeleft') {
        $('#navigationButtons a#navNextPicture i').trigger('click')
      } else if (event.type == 'swiperight') {
        $('#navigationButtons a#navPrevPicture i').trigger('click')
      } else {
        return
      }
    })
  }

  $('[data-action="changeImgSrc"]').on('click', function (e) {
    const mainImage = document.getElementById('mainImageTag')
    const url = $(this).data('url')
    const typeMap = $(this).data('type-map')
    if (mainImage) {
      mainImage.removeAttribute('width')
      mainImage.removeAttribute('height')
      mainImage.src = url
    }
    $('.derivative-li').removeClass('active')
    $(`#derivative${typeMap}`).addClass('active')
    setCookie('picture_deriv', typeMap)
  })

  if ($('.comments-list').length) {
    $('.comment [data-role="delete-comment"]').on('click', function () {
      return confirm(confirm_message)
    })
  }
})
