$(function () {
  function checkOrderOptions() {
    $('#image_order_user_define_options').hide()
    if ($('input[name=image_order_choice]:checked').val() == 'user_define') {
      $('#image_order_user_define_options').show()
    }
  }

  $('ul.thumbnails').sortable({
    revert: true,
    opacity: 0.7,
    handle: $('.rank-of-image').add('.rank-of-image img'),
    update: function () {
      $(this)
        .find('li')
        .each(function (i) {
          $(this)
            .find('input[name^=rank_of_image]')
            .each(function () {
              $(this).attr('value', (i + 1) * 10)
            })
        })

      $('#image_order_rank').prop('checked', true)
      checkOrderOptions()
    },
  })

  $('input[name=image_order_choice]').on('click', function () {
    checkOrderOptions()
  })

  checkOrderOptions()
})
