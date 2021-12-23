import plupload from 'plupload'

$(function () {
  if (phyxo === undefined || phyxo.uploader_id === undefined) {
    return
  }

  const uploadedPhotos = []
  let filesList = []
  let uploadCategory = null

  const startUploadButton = $('#startUpload')
  const addFilesButton = $('#addFiles')
  const cancelUploadButton = $('#cancelUpload')

  const uploadingErrorArea = $('#uploadingError')
  const uploadedPhotosArea = $('#uploadedPhotos')

  const uploader = new plupload.Uploader({
    browse_button: 'addFiles',
    container: document.getElementById(phyxo.uploader_id),
    runtimes: 'html5',
    url: ws_url + '?method=pwg.images.upload',
    chunk_size: '500kb',
    filters: {
      max_file_size: '1000mb',
      mime_types: [{ title: 'Image files', extensions: phyxo_msg.file_exts }],
    },
    dragdrop: true,
  })

  uploader.bind('Init', function (up, res) {
    uploadingErrorArea.hide()
    uploadedPhotosArea.hide()

    $('.infos').hide()
    $('.hide-before-upload').hide()
    $('.show-before-upload').show()

    startUploadButton.prop('disabled', true)
    $('#uploadingActions').hide()

    cancelUploadButton.on('click', function (e) {
      e.preventDefault()
      up.stop()
      up.trigger('UploadComplete', up.files)
    })
  })

  uploader.bind('FilesAdded', function (up, files) {
    filesList = [...filesList, ...files]

    const uploader_filelist = $('#uploader_filelist')
    uploader_filelist.html('')

    filesList.forEach((file) => {
      uploader_filelist.append(addImage(file))
      handleStatus(file)

      $(`#${file.id}.plupload_delete a`).on('click', function (e) {
        $(`#${file.id}`).remove()
        up.removeFile(file)

        e.preventDefault()
      })
    })

    updateTotalProgress(up)
  })

  uploader.bind('FilesRemoved', function (up, files) {
    const ids = files.map((file) => file.id)
    filesList = filesList.filter((file) => !ids.includes(file.id))
    updateTotalProgress(up)
  })

  uploader.bind('QueueChanged', function (up) {
    startUploadButton.prop('disabled', up.files.length === 0)
    updateTotalProgress(up)
  })

  uploader.bind('UploadProgress', function (up, file) {
    $(`#${file.id} .plupload_file_status`).html(file.percent + '%')

    handleStatus(file)
    updateTotalProgress(up)
  })

  uploader.bind('BeforeUpload', function (up, file) {
    startUploadButton.hide()
    addFilesButton.hide()
    uploadingErrorArea.show()
    uploadedPhotosArea.show()
    $('#uploadingActions').show()

    $('select[name="level"]').attr('disabled', 'disabled')
    up.setOption('multipart_params', {
      category: $('select[name="category"] option:selected').val(),
      level: $('select[name="level"] option:selected').val(),
    })
  })

  uploader.bind('FileUploaded', function (up, file, info) {
    handleStatus(file)

    const data = JSON.parse(info.response)
    const image = `<a href="${phyxo_msg.u_edit_pattern.replace(
      0,
      data.result.image_id
    )}"><img src="${data.result.src}" class="thumbnail" title="${
      data.result.name
    }" alt=""></a>`
    uploadedPhotosArea.find('.photos').prepend(image)

    uploadedPhotos.push(parseInt(data.result.image_id))
    uploadCategory = data.result.category
  })

  uploader.bind('StateChanged', function (up) {
    if (up.state === plupload.STARTED) {
      $(
        'li.plupload_delete a,div.plupload_buttons',
        `#${phyxo.uploader_id}`
      ).hide()
      up.disableBrowse(true)

      $(
        'span.plupload_upload_status,div.plupload_progress,a.plupload_stop',
        `#${phyxo.uploader_id}`
      ).show()
    } else {
      $('a.plupload_delete', `#${phyxo.uploader_id}`).css('display', 'block')
    }
  })

  uploader.bind('UploadComplete', function (up, files) {
    $('.infos').append(
      `<ul><li>${sprintf(
        phyxo_msg.photosUploaded_label,
        uploadedPhotos.length
      )}</li></ul>`
    )

    const album_link = `<a href="${phyxo_msg.u_album_pattern.replace(
      '0',
      uploadCategory.id
    )}">${uploadCategory.label}</a>`

    const html_link = sprintf(
      phyxo_msg.albumSummary_label,
      album_link,
      parseInt(uploadCategory.nb_photos)
    )

    $('.infos ul').append(`<li>${html_link}</li>`)
    $('.infos').show()

    $('#batch_photos').val(uploadedPhotos.join(','))

    $('.afterUploadActions')
      .find('input[type="submit"]')
      .attr('value', sprintf(phyxo_msg.batch_label, uploadedPhotos.length))

    $('.show-after-upload').show()
    $('.hide-after-upload').hide()

    $('#uploadingActions').hide()
    $(document).off('beforeunload')
  })

  uploader.bind('Error', function (up, err) {
    const file = err.file
    let message

    if (file) {
      message = err.message

      if (err.details) {
        message += ' (' + err.details + ')'
      }

      if (err.code == plupload.FILE_SIZE_ERROR) {
        message += ' - ' + phyxo_msg.file_too_large + ' ' + file.name
      }

      if (err.code == plupload.FILE_EXTENSION_ERROR) {
        message += ' - ' + phyxo_msg.invalid_file_extension + ' ' + file.name
      }

      file.hint = message
      $(`#${file.id}`)
        .attr('class', 'plupload_failed')
        .find('a')
        .css('display', 'block')
        .attr('title', message)
    }

    uploadingErrorArea.find('.messages').append(`<p>${message}</p>`)
  })

  startUploadButton.on('click', function (e) {
    e.preventDefault()
    uploader.start()
  })

  uploader.init()
})

function addImage(file) {
  const li = document.createElement('li')
  li.setAttribute('id', file.id)

  const div_name = document.createElement('div')
  div_name.setAttribute('class', 'plupload_file_name')
  div_name.appendChild(document.createTextNode(file.name))

  const div_action = document.createElement('div')
  div_action.setAttribute('class', 'plupload_file_action')
  const a_action = document.createElement('a')
  a_action.setAttribute('href', '#')
  div_action.appendChild(a_action)

  const div_status = document.createElement('div')
  div_status.setAttribute('class', 'plupload_file_status')
  div_status.appendChild(document.createTextNode(file.percent + '%'))

  const div_size = document.createElement('div')
  div_size.setAttribute('class', 'plupload_file_size')
  div_size.appendChild(document.createTextNode(formatSize(file.size)))

  const div_clearer = document.createElement('div')
  div_clearer.setAttribute('class', 'plupload_clearer')
  div_clearer.appendChild(document.createTextNode('&nbsp;'))

  li.appendChild(div_name)
  li.appendChild(div_action)
  li.appendChild(div_status)
  li.appendChild(div_size)
  li.appendChild(div_clearer)

  return li
}

function updateTotalProgress(uploader) {
  $('.plupload_progress_bar', `#${phyxo.uploader_id}`).css(
    'width',
    uploader.total.percent + '%'
  )

  $('.plupload_total_status', `#${phyxo.uploader_id}`).html(
    uploader.total.percent + '%'
  )

  $('.plupload_total_file_size', `#${phyxo.uploader_id}`).html(
    formatSize(uploader.total.size)
  )
}

function handleStatus(file) {
  let actionClass

  if (file.status == plupload.DONE) {
    actionClass = 'plupload_done'
  }

  if (file.status == plupload.FAILED) {
    actionClass = 'plupload_failed'
  }

  if (file.status == plupload.QUEUED) {
    actionClass = 'plupload_delete'
  }

  if (file.status == plupload.UPLOADING) {
    actionClass = 'plupload_uploading'
  }

  const icon = $(`#${file.id}`)
    .attr('class', actionClass)
    .find('a')
    .css('display', 'block')
  if (file.hint) {
    icon.attr('title', file.hint)
  }
}

function formatSize(size) {
  if (size === undefined || /\D/.test(size)) {
    return phyxo_msg.sizes.n_a
  }

  function round(num, precision) {
    return Math.round(num * Math.pow(10, precision)) / Math.pow(10, precision)
  }

  let boundary = Math.pow(1024, 4)

  // TB
  if (size > boundary) {
    return round(size / boundary, 1) + ' ' + phyxo_msg.sizes['tb']
  }

  // GB
  if (size > (boundary /= 1024)) {
    return round(size / boundary, 1) + ' ' + phyxo_msg.sizes['gb']
  }

  // MB
  if (size > (boundary /= 1024)) {
    return round(size / boundary, 1) + ' ' + phyxo_msg.sizes['mb']
  }

  // KB
  if (size > 1024) {
    return Math.round(size / 1024) + ' ' + phyxo_msg.sizes['kb']
  }

  return size + ' ' + phyxo_msg.sizes['b']
}
