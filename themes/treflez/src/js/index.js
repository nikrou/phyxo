import '../scss/theme.scss'

import 'bootstrap'
import './keyboard-navigation'
import './tags'
import './thumbnails-loader'
import './picture'
import './picture-tags'
import './search'
import './ws'
import './slick'
import './photoswipe'
import './grid-view'
import 'jquery.cookie'

$(function () {
  $('#categoriesDropdownMenu').on('show.bs.dropdown', function () {
    $(this)
      .find('a.dropdown-item')
      .each(function () {
        const level = $(this).data('level')
        const padding = parseInt($(this).css('padding-left'))
        if (level > 0) {
          $(this).css('padding-left', padding + 10 * level + 'px')
        }
      })
  })

  const qsearch_icon = $('#navbar-menubar > #quicksearch > .fa-search')
  const qsearch_text = $('#navbar-menubar > #quicksearch #qsearchInput')
  $(qsearch_icon).on('click', function () {
    $(qsearch_text).trigger('focus')
  })
  $('#navbar-menubar > #quicksearch').css({
    color: $('#navbar-menubar .nav-link').css('color'),
  })

  $('.navbar-main .navbar-collapse').on('show.bs.collapse', function () {
    $('.navbar-main').attr(
      'style',
      'background-color: rgba(0, 0, 0, 0.9) !important'
    )
  })
  $('.navbar-main .navbar-collapse').on('hidden.bs.collapse', function () {
    $('.navbar-main').attr('style', '')
  })

  // move to main navbar to avoid scrolling issues in navmenu on mobile devices
  $('#languageSwitch').appendTo('#navbar-menubar > ul.navbar-nav')

  $('#show_exif_data').on('click', function () {
    if ($('#full_exif_data').hasClass('d-none')) {
      $('#full_exif_data').addClass('d-flex').removeClass('d-none')
      $('#show_exif_data').html(
        '<i class="fa fa-info mr-1"></i> ' + phyxo_hide_exif_data
      )
    } else {
      $('#full_exif_data').addClass('d-none').removeClass('d-flex')
      $('#show_exif_data').html(
        '<i class="fa fa-info mr-1"></i> ' + phyxo_show_exif_data
      )
    }
  })

  const cookie_name = 'sidebar_infos'
  const cookie_params = { path: phyxo_root_url }

  // Side bar
  const sidebar = $('#sidebar')
  if (
    $.cookie(cookie_name) !== undefined &&
    $.cookie(cookie_name) === 'opened'
  ) {
    sidebar.css('right', 0)
  }

  const navigationButtons = $('#navigationButtons')
  if (sidebar.length && navigationButtons.length) {
    sidebar.css('top', navigationButtons.offset().top + 1 + 'px')
    $('#info-link').on('click', function () {
      if (parseInt(sidebar.css('right'), 10) < 0) {
        sidebar.animate({ right: '+=250' }, 500)
        $.cookie(cookie_name, 'opened', cookie_params)
      } else {
        sidebar.animate({ right: '-=250' }, 500)
        $.cookie(cookie_name, 'closed', cookie_params)
      }

      return false
    })
  }
})
