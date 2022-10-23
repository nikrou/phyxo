$(function () {
  $('input[name="submit"]').on('click', function () {
    if (!confirm(phyxo_msg.are_you_sure)) {
      return false
    }
    $(this).hide()
    $('.autoupdate_bar').show()
  })
  $('[name="understand"]').on('click', function () {
    $('[name="submit"]').attr('disabled', !this.checked)
  })
})
