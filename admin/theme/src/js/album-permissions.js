import 'selectize'
import './LocalStorageCache'

$(function () {
  if (
    phyxo === undefined ||
    phyxo.groupsCache === undefined ||
    phyxo.usersCache === undefined
  ) {
    return
  }

  const groupsCache = new GroupsCache(phyxo.groupsCache)
  groupsCache.selectize($('[data-selectize="groups"]'))

  const usersCache = new UsersCache(phyxo.usersCache)
  usersCache.selectize($('[data-selectize=users]'))

  function checkStatusOptions() {
    if ($('input[name=status]:checked').val() == 'private') {
      $('#privateOptions, #applytoSubAction').show()
    } else {
      $('#privateOptions, #applytoSubAction').hide()
    }
  }

  checkStatusOptions()
  $('input[name=status]').on('change', function () {
    checkStatusOptions()
  })
})
