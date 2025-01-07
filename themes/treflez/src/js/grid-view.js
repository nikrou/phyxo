import 'jquery.cookie'
import 'jquery-equalheights'

$(function () {
  const cookie_name = 'album_view'
  const cookie_params = { path: phyxo_root_url }

  // Grid view button click
  $('#btn-grid').click(function () {
    if ($(this).hasClass('active')) {
      return
    }

    $.cookie(cookie_name, 'grid', cookie_params)
    $('#btn-grid').addClass('active')
    $('#btn-list').removeClass('active')
    $('#content')
      .removeClass('content-list')
      .addClass('content-grid')
      .find('.col-outer')
      .each(function () {
        $(this).find('.card-body').attr('style', '')
        $(this)
          .find('a')
          .addClass('d-block')
          .find('.card-img-left')
          .addClass('card-img-top')
          .removeClass('card-img-left')
        $(this).find('.card-body.list-view-only').addClass('d-none')
        $(this).removeClass('col-12').addClass($(this).data('grid-classes'))
      })
  })

  // List view button click
  $('#btn-list').click(function () {
    if ($(this).hasClass('active')) {
      return
    }
    $.cookie(cookie_name, 'list', cookie_params)
    $('#btn-list').addClass('active')
    $('#btn-grid').removeClass('active')
    $('#content')
      .removeClass('content-grid')
      .addClass('content-list')
      .height('auto')
      .find('.col-outer')
      .each(function () {
        $(this)
          .find('a')
          .removeClass('d-block')
          .find('.card-img-top')
          .addClass('card-img-left')
          .removeClass('card-img-top')
        $(this).find('.card-body.list-view-only').removeClass('d-none')
        $(this).removeClass($(this).data('grid-classes')).addClass('col-12')
      })
  })
})
