import '../scss/style.scss'

import 'bootstrap'
import './intro'
import './cat-list'
import './users-list'
import './groups-list'
import './plugins-list'
import './extension'
import './comments'
import './tags'
import 'jquery-colorbox'
import './add_album'
import './maintenance'
import './album-permissions'
import './album-properties'
import './photo-properties'
import './batch_manager_global'
import './batch_manager_unit'
import './photo-coi'
import './element_set_ranks'
import './menubar'
import './photos_add_direct'
import './update'

$(function () {
  $('#sidebarCollapse').on('click', function () {
    $('#sidebar').toggleClass('active')
  })

  $('.extensions .collapse')
    .on('show.bs.collapse', ({ currentTarget }) => {
      const $icon = $(currentTarget).parent().find('i')
      $icon.removeClass('fa-plus-square-o').addClass('fa-minus-square-o')
    })
    .on('hidden.bs.collapse', ({ currentTarget }) => {
      const $icon = $(currentTarget).parent().find('i')
      $icon.removeClass('fa-minus-square-o').addClass('fa-plus-square-o')
    })

  if (menuitem_active !== undefined) {
    $('aside[role="navigation"] a[href="' + menuitem_active + '"]')
      .addClass('show')
      .parentsUntil('.accordion')
      .addClass('show')
  }

  $('a.externalLink').on('click', function () {
    window.open(this.attr('href'))

    return false
  })

  $('[data-confirm]').on('click', function () {
    const button = $(this)
    const fetch_url = button.data('action')
    const http_method =
      button.data('method') !== undefined ? button.data('method') : 'GET'
    let fetch_params = {}
    if (http_method === 'POST') {
      fetch_params.headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
      } // @TODO: send application/json but need to retrieve stream in WS
      const post_params = []
      for (let param in button.data('data')) {
        post_params.push(
          param + '=' + encodeURIComponent(button.data('data')[param])
        )
      }
      fetch_params.body = post_params.join('&')
    } else {
      fetch_params = button.data('data')
    }
    fetch_params.method = http_method
    fetch_params.credentials = 'same-origin'

    $('#confirm-delete').on('click', '.btn.btn-delete', function (e) {
      fetch(fetch_url, fetch_params)
        .then(function (response) {
          if (response.ok) {
            $(button.data('delete')).remove() // @TODO: need to remove whole table row
          } else {
            console.log(response.statusText)
          }
        })
        .catch(function (err) {
          console.log(err)
        })
    })
  })

  $.fn.fontCheckbox = function () {
    this.find('input[type="checkbox"], input[type="radio"]').each(function () {
      if (!$(this).is(':checked')) {
        $(this).prev().toggleClass('fa-check-square fa-square-o')
      }
    })
    this.find('input[type="checkbox"]').on('change', function () {
      $(this).prev().toggleClass('fa-check-square fa-square-o')
    })
    this.find('input[type="radio"]').on('change', function () {
      $(this)
        .closest('.font-checkbox')
        .find('input[type="radio"][name="' + $(this).attr('name') + '"]')
        .prev()
        .toggleClass('fa-check-square fa-square-o')
    })
  }

  // init fontChecbox everywhere
  $('.font-checkbox').fontCheckbox()

  const order_filters = $('#order_filters')
  order_filters
    .on('click', '.add-filter', function (e) {
      e.preventDefault()

      const firstFilter = order_filters.find('.filter:first')
      const newFilter = firstFilter.clone()
      newFilter.find('.btn.remove-filter').prop('disabled', false)
      newFilter.appendTo(order_filters)
    })
    .on('click', '.remove-filter', function (e) {
      $(this).parents('.filter:first').remove()

      e.preventDefault()

      return false
    })

  $('.preview-box').colorbox()

  $("input[name='mail_theme']").on('change', function () {
    $("input[name='mail_theme']")
      .parents('.themeBox')
      .removeClass('themeDefault')
    $(this).parents('.themeBox').addClass('themeDefault')
  })

  const TARGETS = {
    'input[name="rate"]': '#rate_anonymous',
    'input[name="allow_user_registration"]': '#email_admin_on_new_user',
    'input[name="comments_validation"]': '#email_admin_on_comment_validation',
    'input[name="user_can_edit_comment"]': '#email_admin_on_comment_edition',
    'input[name="user_can_delete_comment"]': '#email_admin_on_comment_deletion',
  }

  for (let selector in TARGETS) {
    const target = TARGETS[selector]

    $(target).toggle($(selector).is(':checked'))
    ;(function (target) {
      $(selector).on('change', function () {
        $(target).toggle($(this).is(':checked'))
      })
    })(target)
  }

  $('#addWatermark').on('click', () => {
    $('#new-watermark').toggleClass('d-none')
    $('#add-watermark').toggleClass('d-none')
  })

  $('#newWatermark').on('click', () => {
    $('#add-watermark').toggleClass('d-none')
    $('#new-watermark').toggleClass('d-none')
  })
  $('input[name="watermark[position]"]').on('change', function () {
    if ($(this).val() === 'custom') {
      $('#positionCustomDetails').removeClass('d-none')
    } else {
      $('#positionCustomDetails').addClass('d-none')
    }
  })

  $('select[name="watermark[file]"').on('change', function (e) {
    $('#seleted-watermark-file').attr('src', phyxo_root_url + $(this).val())
  })

  $('.alert').alert()

  // notification by mail
  $('#checkAllLink').on('click', function () {
    $('#notification_by_mail input[type="checkbox"]').prop('checked', true)

    return false
  })

  $('#uncheckAllLink').on('click', function () {
    $('#notification_by_mail input[type="checkbox"]').prop('checked', false)

    return false
  })
})
