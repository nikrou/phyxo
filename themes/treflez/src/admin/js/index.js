import '../scss/admin.scss'

$(function () {
  $('ul.nav-tabs li a').click(function (e) {
    e.preventDefault()

    var tab_id = $(this).attr('href')

    $('ul.nav-tabs li a').removeClass('active')
    $('.tab-content').removeClass('active')

    $(this).addClass('active')
    $(tab_id).addClass('active')
  })

  $('select[name="page_header"]').change(function () {
    if ($(this).val() == 'fancy') {
      $('.fancy').removeClass('d-none')
    } else {
      $('.fancy').addClass('d-none')
    }
  })

  $('#social_enabled').change(function () {
    if ($(this).is(':checked')) {
      $('.social').removeClass('d-none')
    } else {
      $('.social').addClass('d-none')
    }
  })

  $('input[name="photoswipe"]').change(function () {
    curr = $('select[name="thumbnail_linkto"]').val()
    if (!$(this).is(':checked') && curr !== 'picture') {
      $('select[name="thumbnail_linkto"]').val('picture')
      $('select[name="thumbnail_linkto"] option[value="photoswipe"]').attr(
        'disabled',
        'disabled'
      )
      $(
        'select[name="thumbnail_linkto"] option[value="photoswipe_mobile_only"]'
      ).attr('disabled', 'disabled')
    } else {
      $(
        'select[name="thumbnail_linkto"] option[value="photoswipe"]'
      ).removeAttr('disabled')
      $(
        'select[name="thumbnail_linkto"] option[value="photoswipe_mobile_only"]'
      ).removeAttr('disabled')
    }
  })
})
