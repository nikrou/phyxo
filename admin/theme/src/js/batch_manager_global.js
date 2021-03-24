import './doubleSlider'

var derivatives = {
  elements: null,
  done: 0,
  total: 0,

  finished: function () {
    return (
      derivatives.done == derivatives.total &&
      derivatives.elements &&
      derivatives.elements.length == 0
    )
  },
}

$(function () {
  if ($('#batchManagerGlobal').length === 0) {
    return
  }

  let categoriesCache = null
  if (phyxo.categoriesCache !== undefined) {
    categoriesCache = new CategoriesCache(phyxo.categoriesCache)
  }

  if (associated_categories) {
    categoriesCache.selectize($('[data-selectize=categories]'), {
      filter: function (categories, options) {
        if (this.name == 'dissociate') {
          var filtered = $.grep(categories, function (cat) {
            return !!associated_categories[cat.id]
          })

          if (filtered.length > 0) {
            options.default = filtered[0].id
          }

          return filtered
        } else {
          return categories
        }
      },
    })
  }

  function checkPermitAction() {
    var nbSelected = 0
    if ($('input[name=setSelected]').is(':checked')) {
      nbSelected = nb_thumbs_set
    } else {
      nbSelected = $('.thumbnails input[type=checkbox]').filter(':checked')
        .length
    }

    if (nbSelected == 0) {
      $('#permitAction').hide()
      $('#forbidAction').show()
    } else {
      $('#permitAction').show()
      $('#forbidAction').hide()
    }

    $('#applyOnDetails').text(sprintf(applyOnDetails_pattern, nbSelected))

    // display the number of currently selected photos in the "Selection" fieldset
    if (nbSelected == 0) {
      $('#selectedMessage').text(sprintf(selectedMessage_none, nb_thumbs_set))
    } else if (nbSelected == nb_thumbs_set) {
      $('#selectedMessage').text(sprintf(selectedMessage_all, nb_thumbs_set))
    } else {
      $('#selectedMessage').text(
        sprintf(selectedMessage_pattern, nbSelected, nb_thumbs_set)
      )
    }
  }

  $('[id^=action_]').hide()

  $('select[name=selectAction]').change(function () {
    $('[id^=action_]').hide()
    $('#action_' + $(this).prop('value')).show()

    if ($(this).val() != -1) {
      $('#applyActionBlock').show()
    } else {
      $('#applyActionBlock').hide()
    }
  })

  $('.wrapper-label label').click(function (event) {
    $('input[name=setSelected]').prop('checked', false)

    var wrap2 = $(this).children('.wrapper-thumbnail')
    var checkbox = $(this).children('input[type=checkbox]')

    checkbox.triggerHandler('shclick', event)

    if ($(checkbox).is(':checked')) {
      $(wrap2).addClass('thumbSelected')
    } else {
      $(wrap2).removeClass('thumbSelected')
    }

    checkPermitAction()
  })

  $('#selectAll').click(function () {
    $('input[name=setSelected]').prop('checked', false)
    selectPageThumbnails()
    checkPermitAction()
    return false
  })

  function selectPageThumbnails() {
    $('.thumbnails label').each(function () {
      var wrap2 = $(this).children('.wrapper-thumbnail')
      var checkbox = $(this).children('input[type=checkbox]')

      $(checkbox).prop('checked', true)
      $(wrap2).addClass('thumbSelected')
    })
  }

  $('#selectNone').click(function () {
    $('input[name=setSelected]').prop('checked', false)

    $('.thumbnails label').each(function () {
      var wrap2 = $(this).children('.wrapper-thumbnail')
      var checkbox = $(this).children('input[type=checkbox]')

      $(checkbox).prop('checked', false)
      $(wrap2).removeClass('thumbSelected')
    })
    checkPermitAction()
    return false
  })

  $('#selectInvert').click(function () {
    $('input[name=setSelected]').prop('checked', false)

    $('.thumbnails label').each(function () {
      var wrap2 = $(this).children('.wrapper-thumbnail')
      var checkbox = $(this).children('input[type=checkbox]')

      $(checkbox).prop('checked', !$(checkbox).is(':checked'))

      if ($(checkbox).is(':checked')) {
        $(wrap2).addClass('thumbSelected')
      } else {
        $(wrap2).removeClass('thumbSelected')
      }
    })
    checkPermitAction()
    return false
  })

  $('#selectSet').click(function () {
    selectPageThumbnails()
    $('input[name=setSelected]').prop('checked', true)
    checkPermitAction()
    return false
  })

  $('#applyAction').click(function () {
    var action = $('[name="selectAction"]').val()
    if (action == 'delete_derivatives') {
      var d_count = $('#action_delete_derivatives input[type=checkbox]').filter(
          ':checked'
        ).length,
        e_count = $('input[name="setSelected"]').is(':checked')
          ? nb_thumbs_set
          : $('.thumbnails input[type=checkbox]').filter(':checked').length
      if (d_count * e_count > 500) return confirm(lang.AreYouSure)
    }

    if (action != 'generate_derivatives' || derivatives.finished()) {
      return true
    }

    $('.bulkAction').hide()

    var queuedManager = $.manageAjax.create('queued', {
      queue: true,
      cacheResponse: false,
      maxRequests: 1,
    })

    derivatives.elements = []
    if ($('input[name="setSelected"]').is(':checked'))
      derivatives.elements = all_elements
    else
      $('.thumbnails input[type=checkbox]').each(function () {
        if ($(this).is(':checked')) {
          derivatives.elements.push($(this).val())
        }
      })

    $('#applyActionBlock').hide()
    $('select[name="selectAction"]').hide()
    $('#regenerationMsg').show()

    progress()
    getDerivativeUrls()
    return false
  })

  checkPermitAction()

  $('select[name=filter_prefilter]').change(function () {
    $('#empty_caddie').toggle($(this).val() == 'caddie')
    $('#duplicates_options').toggle($(this).val() == 'duplicates')
  })

  var last_clicked = 0,
    last_clickedstatus = true
  $.fn.enableShiftClick = function () {
    var inputs = [],
      count = 0
    this.find('input[type=checkbox]').each(function () {
      var pos = count
      inputs[count++] = this
      $(this).bind('shclick', function (dummy, event) {
        if (event.shiftKey) {
          var first = last_clicked
          var last = pos
          if (first > last) {
            first = pos
            last = last_clicked
          }

          for (var i = first; i <= last; i++) {
            input = $(inputs[i])
            $(input).prop('checked', last_clickedstatus)
            if (last_clickedstatus) {
              $(input).siblings('.wrapper-thumbnail').addClass('thumbSelected')
            } else {
              $(input)
                .siblings('.wrapper-thumbnail')
                .removeClass('thumbSelected')
            }
          }
        } else {
          last_clicked = pos
          last_clickedstatus = this.checked
        }
        return true
      })
      $(this).click(function (event) {
        $(this).triggerHandler('shclick', event)
      })
    })
  }
  $('ul.thumbnails').enableShiftClick()

  $('.removeFilter').click(function () {
    console.log('clic removeFilter')
    var filter = $(this).parent('li').attr('id')
    filter_disable(filter)

    return false
  })

  $('#addFilter').change(function () {
    var filter = $(this).prop('value')
    filter_enable(filter)
    $(this).prop('value', -1)
  })

  $('#removeFilters').click(function () {
    $('#filterList li').each(function () {
      var filter = $(this).attr('id')
      filter_disable(filter)
    })
    return false
  })

  $('[data-add-album]').pwgAddAlbum({ cache: categoriesCache })

  $('input[name=remove_author]').click(function () {
    if ($(this).is(':checked')) {
      $('input[name=author]').hide()
    } else {
      $('input[name=author]').show()
    }
  })

  $('input[name=remove_title]').click(function () {
    if ($(this).is(':checked')) {
      $('input[name=title]').hide()
    } else {
      $('input[name=title]').show()
    }
  })

  $('input[name=remove_date_creation]').click(function () {
    if ($(this).is(':checked')) {
      $('#set_date_creation').hide()
    } else {
      $('#set_date_creation').show()
    }
  })
})

function filter_enable(filter) {
  /* show the filter*/
  $('#' + filter).show()

  /* check the checkbox to declare we use this filter */
  $('input[type=checkbox][name=' + filter + '_use]').prop('checked', true)

  /* forbid to select this filter in the addFilter list */
  $('#addFilter')
    .children('option[value=' + filter + ']')
    .attr('disabled', 'disabled')
}

function filter_disable(filter) {
  /* hide the filter line */
  $('#' + filter).hide()

  /* uncheck the checkbox to declare we do not use this filter */
  $('input[name=' + filter + '_use]').prop('checked', false)

  /* give the possibility to show it again */
  $('#addFilter')
    .children('option[value=' + filter + ']')
    .removeAttr('disabled')
}

function progress(success) {
  $('#progressBar').progressBar(derivatives.done, {
    max: derivatives.total,
    textFormat: 'fraction',
    boxImage: 'theme/images/progressbar.gif',
    barImage: 'theme/images/progressbg_orange.gif',
  })
  if (success !== undefined) {
    var type = success ? 'regenerateSuccess' : 'regenerateError',
      s = $('[name="' + type + '"]').val()
    $('[name="' + type + '"]').val(++s)
  }

  if (derivatives.finished()) {
    $('#applyAction').click()
  }
}

function getDerivativeUrls() {
  var ids = derivatives.elements.splice(0, 500)
  var params = { max_urls: 100000, ids: ids, types: [] }
  $('#action_generate_derivatives input').each(function (i, t) {
    if ($(t).is(':checked')) params.types.push(t.value)
  })

  $.ajax({
    type: 'POST',
    url: 'ws?method=pwg.getMissingDerivatives',
    data: params,
    dataType: 'json',
    success: function (data) {
      if (!data.stat || data.stat != 'ok') {
        return
      }
      derivatives.total += data.result.urls.length
      progress()
      for (var i = 0; i < data.result.urls.length; i++) {
        $.manageAjax.add('queued', {
          type: 'GET',
          url: data.result.urls[i] + '&ajaxload=true',
          dataType: 'json',
          success: function (data) {
            derivatives.done++
            progress(true)
          },
          error: function (data) {
            derivatives.done++
            progress(false)
          },
        })
      }
      if (derivatives.elements.length)
        setTimeout(
          getDerivativeUrls,
          25 * (derivatives.total - derivatives.done)
        )
    },
  })
}

function selectGenerateDerivAll() {
  $('#action_generate_derivatives input[type=checkbox]').prop('checked', true)
}
function selectGenerateDerivNone() {
  $('#action_generate_derivatives input[type=checkbox]').prop('checked', false)
}

function selectDelDerivAll() {
  $('#action_delete_derivatives input[type=checkbox]').prop('checked', true)
}
function selectDelDerivNone() {
  $('#action_delete_derivatives input[type=checkbox]').prop('checked', false)
}
