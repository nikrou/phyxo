import { checkUpdates } from './services'

$(function () {
  const $alertBox = $('section[role="content"] .alert.hide')

  $('#check-upgrade').click(function (e) {
    checkUpdates().then((data) => {
      if (data['stat'] != 'ok') {
        return
      }

      phyxo_update = data.result.phyxo_need_update
      ext_update = data.result.ext_need_update
      if (phyxo_update || ext_update) {
        $alertBox.addClass('alert-warning')
        if (ext_update) {
          $alertBox.prepend('<p>' + ext_need_update_msg + '</p>')
        }
        if (phyxo_update) {
          $alertBox.prepend('<p>' + phyxo_need_update_msg + '</p>')
        }
      } else {
        $alertBox
          .addClass('alert-success')
          .prepend('<p>' + phyxo_is_uptodate_msg + '</p>')
      }
      $alertBox.removeClass('hide').addClass('show')
      $('#check-upgrade').parent().remove()
    })
    e.preventDefault()
  })
})
