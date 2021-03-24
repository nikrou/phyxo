import 'selectize'
import './LocalStorageCache'

$(function () {
  if ($('#addAlbumForm').length === 0) {
    return
  }

  if (phyxo === undefined || phyxo.categoriesCache === undefined) {
    return
  }

  const categoriesCache = new CategoriesCache(phyxo.categoriesCache)
  categoriesCache.selectize($('[data-selectize=categories]'), {
    filter: function (categories, options) {
      if (categories.length > 0) {
        jQuery('#albumSelection, .selectFiles, .showFieldset').show()
      }

      return categories
    },
  })

  $('[data-add-album]').pwgAddAlbum({ cache: categoriesCache })
})

$.fn.pwgAddAlbum = function (options) {
  if (!options.cache) {
    $.error('pwgAddAlbum: missing categories cache')
  }

  var $popup = $('#addAlbumForm')

  function init() {
    if ($popup.data('init')) {
      return
    }
    $popup.data('init', true)

    options.cache.selectize($popup.find('[name="category_parent"]'), {
      default: 0,
      filter: function (categories) {
        categories.push({
          id: 0,
          fullname: '------------',
          global_rank: 0,
        })

        return categories
      },
    })

    $popup.find('form').on('submit', function (e) {
      e.preventDefault()

      $('#categoryNameError').text('')

      var albumParent = $popup.find('[name="category_parent"]'),
        parent_id = albumParent.val(),
        name = $popup.find('[name=category_name]').val(),
        target = $popup.data('target')

      $.ajax({
        url: ws_url,
        type: 'POST',
        dataType: 'json',
        data: {
          method: 'pwg.categories.add',
          parent: parent_id,
          name: name,
        },
        beforeSend: function () {
          $('#albumCreationLoading').show()
        },
        success: function (data) {
          $('#albumCreationLoading').hide()
          $('[data-add-album="' + target + '"]').colorbox.close()

          var newAlbum = data.result.id,
            newAlbum_name = '',
            newAlbum_rank = '0'

          if (parent_id != 0) {
            newAlbum_name =
              albumParent[0].selectize.options[parent_id].fullname + ' / '
            newAlbum_rank =
              albumParent[0].selectize.options[parent_id].global_rank + '.1'
          }
          newAlbum_name += name

          var $albumSelect = $('[name="' + target + '"]')

          // target is a normal select
          if (!$albumSelect[0].selectize) {
            var new_option = $('<option/>')
              .attr('value', newAlbum)
              .attr('selected', 'selected')
              .text(newAlbum_name)

            $albumSelect.find('option').removeAttr('selected')

            if (parent_id == 0) {
              $albumSelect.prepend(new_option)
            } else {
              $albumSelect
                .find('option[value=' + parent_id + ']')
                .after(new_option)
            }
          } else {
            var selectize = $albumSelect[0].selectize

            if ($.isEmptyObject(selectize.options)) {
              options.cache.clear()
              options.cache.selectize($albumSelect, {
                default: newAlbum,
                value: newAlbum,
              })
            } else {
              $albumSelect[0].selectize.addOption({
                id: newAlbum,
                fullname: newAlbum_name,
                global_rank: newAlbum_rank,
              })

              $albumSelect[0].selectize.setValue(newAlbum)
            }
          }

          albumParent.val('')
          $('#albumSelection, .selectFiles, .showFieldset').show()
        },
        error: function (XMLHttpRequest, textStatus, errorThrows) {
          $('#albumCreationLoading').hide()
          $('#categoryNameError').text(errorThrows).css('color', 'red')
        },
      })
    })
  }

  this.colorbox({
    inline: true,
    href: '#addAlbumForm',
    onComplete: function () {
      init()
      $popup.data('target', $(this).data('addAlbum'))
      $popup.find('[name=category_name]').focus()
    },
  })

  return this
}
