$(function () {
  const uploadPhotos = $('#uploadedPhotos')
  if (uploadPhotos.length === 0) {
    return
  }

  $('#uploadedPhotos').parent().hide()
  $('#uploadWarningsSummary a.showInfo').click(function () {
    $('#uploadWarningsSummary').hide()
    $('#uploadWarnings').show()
    return false
  })

  $('#uploader').pluploadQueue({
    browse_button: 'addFiles',
    runtimes: 'html5',
    url: ws_url + '?method=pwg.images.upload',
    chunk_size: '500kb',
    filters: {
      max_file_size: '1000mb',
      mime_types: [{ title: 'Image files', extensions: file_exts }],
    },
    dragdrop: true,
    preinit: {
      Init: function (up, info) {
        $('#uploader_container').removeAttr('title')

        $('#startUpload').on('click', function (e) {
          e.preventDefault()
          up.start()
        })

        $('#cancelUpload').on('click', function (e) {
          e.preventDefault()
          up.stop()
          up.trigger('UploadComplete', up.files)
        })
      },
    },

    init: {
      // update custom button state on queue change
      QueueChanged: function (up) {
        $('#startUpload').prop('disabled', up.files.length == 0)
      },

      UploadProgress: function (up, file) {
        $('#uploadingActions .progressbar').width(up.total.percent + '%')
      },

      BeforeUpload: function (up, file) {
        $('#startUpload, #addFiles').hide()
        $('#uploadingActions').show()

        $(window).bind('beforeunload', function () {
          return 'Upload in progress'
        })

        $('select[name=level]').attr('disabled', 'disabled')
        up.setOption('multipart_params', {
          category: $('select[name=category] option:selected').val(),
          level: $('select[name=level] option:selected').val(),
          pwg_token: pwg_token,
        })
      },

      FileUploaded: function (up, file, info) {
        $('#' + file.id).hide()
        const data = $.parseJSON(info.response)
        $('#uploadedPhotos').parent().show()
        let html =
          '<a href="' + u_edit_pattern.replace(0, data.result.image_id) + '">'
        html +=
          '<img src="' +
          data.result.src +
          '" class="thumbnail" title="' +
          data.result.name +
          '" alt="">'
        html += '</a> '

        $('#uploadedPhotos').prepend(html)

        uploadedPhotos.push(parseInt(data.result.image_id))
        uploadCategory = data.result.category
      },

      UploadComplete: function (up, files) {
        $('.selectAlbum, .selectFiles, #permissions, .showFieldset').hide()
        $('.infos').append(
          '<ul><li>' +
            sprintf(photosUploaded_label, uploadedPhotos.length) +
            '</li></ul>'
        )

        const album_link = `<a href="${u_album_pattern.replace(
          '0',
          uploadCategory.id
        )}">${uploadCategory.label}</a>`

        const html_link = sprintf(
          albumSummary_label,
          album_link,
          parseInt(uploadCategory.nb_photos)
        )

        $('.infos ul').append('<li>' + html_link + '</li>')
        $('.infos').show()

        $('#batch_photos').val(uploadedPhotos.join(','))
        $('.afterUploadActions')
          .find('input[type="submit"]')
          .attr('value', sprintf(batch_Label, uploadedPhotos.length))
        $('.afterUploadActions').removeClass('d-none')
        $('#uploadingActions').hide()
        $(window).unbind('beforeunload')
      },
    },
  })
})
