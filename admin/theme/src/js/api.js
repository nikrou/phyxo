import '../scss/api.scss'

$(function () {
  const cachedMethods = []

  getMethodList()

  $('#invokeMethod').click(function () {
    const method = $('#methodName').html()
    callMethod(method, false)

    return false
  })

  function resetDisplay() {
    $(
      '#errorWrapper, #methodWrapper, #methodName, #methodDescription, #requestDisplay, #results'
    ).hide()
    $('#methodDescription blockquote').empty()
  }

  function displayError(error) {
    resetDisplay()
    $('#errorWrapper')
      .html('<b>Error:</b> ' + error)
      .show()
  }

  function getMethodList() {
    resetDisplay()

    fetch(
      ws_url +
        '?' +
        new URLSearchParams({
          method: 'reflection.getMethodList',
        })
    )
      .then((data) => data.json())
      .then((response) => {
        const methods = response.result.methods
        const methodList = methods.map(
          (method) =>
            `<button type="button" class="method list-group-item list-group-item-action">${method}</button>`
        )
        $('#methodsList').html(methodList.join('')).show()

        $('#methodsList button.method').on('click', function () {
          selectMethod($(this).html())
        })
      })
      .catch((err) => displayError)
  }

  function selectMethod(methodName) {
    resetDisplay()
    $('#introMessage').hide()
    if (cachedMethods.methodName) {
      fillNewMethod(methodName)
    }

    fetch(
      ws_url +
        '?' +
        new URLSearchParams({
          method: 'reflection.getMethodDetails',
          methodName,
        })
    )
      .then((data) => data.json())
      .then((response) => response.result)
      .then((result) => {
        if (result.options.post_only || result.options.admin_only) {
          let onlys = '<div class="btn btn-danger admin">'
          if (result.options.post_only) {
            onlys += 'POST only. '
          }

          if (result.options.admin_only) {
            onlys += 'Admin only. '
          }

          onlys += '</div>'

          result.description = onlys + result.description
        }
        cachedMethods[methodName] = result
        fillNewMethod(methodName)
      })
      .catch((err) => displayError)
  }

  function fillNewMethod(methodName) {
    resetDisplay()

    const method = cachedMethods[methodName]

    $('#methodName').html(method.name).show()

    if (method.description != '') {
      $('#methodDescription blockquote').html(method.description)
      $('#methodDescription').show()
    }

    $('#requestFormat').val(method.options.post_only ? 'post' : 'get')

    var methodParams = ''
    if (method.params && method.params.length > 0) {
      method.params.forEach((param) => {
        const isOptional = param.optional
        const acceptArray = param.acceptArray
        const defaultValue =
          param.defaultValue == null ? '' : param.defaultValue
        const info =
          param.info == null
            ? ''
            : `<a class="methodInfo" title="${param.info.replace(
                /"/g,
                '&quot;'
              )}">i</a>`
        let type = ''

        if (param.type.match(/bool/)) type += '<span class=type>B</span>'
        if (param.type.match(/int/)) type += '<span class=type>I</span>'
        if (param.type.match(/float/)) type += '<span class=type>F</span>'
        if (param.type.match(/positive/)) type += '<span class=subtype>+</span>'
        if (param.type.match(/notnull/))
          type += '<span class=subtype>&oslash;</span>'

        // if an array is direclty printed, the delimiter is a comma where we use a pipe
        if (typeof defaultValue === 'object') {
          defaultValue = defaultValue.join('|')
        }

        methodParams += `<tr>
          <td>${param.name} ${info}</td>
          <td>${isOptional ? '?' : '*'} ${acceptArray ? ' []' : ''}</td>
          <td>${type}</td>
          <td><input type="text" class="form-control methodParameterValue" data-id="${
            param.name
          }" value="${defaultValue}"></td>
          <td><input type="checkbox" class="form-check-input methodParameterSend" data-id="${
            param.name
          }" ${isOptional ? '' : 'checked="checked"'}></td>
        </tr>`
      })
    } else {
      methodParams =
        '<tr><td colspan="5">This method takes no parameters</td></tr>'
    }

    $('#methodParams tbody').html(methodParams)
    $('#methodWrapper').show()

    $('input.methodParameterValue').on('change', function () {
      $("input.methodParameterSend[data-id='" + $(this).data('id') + "']").attr(
        'checked',
        'checked'
      )
    })
  }

  function callMethod(methodName) {
    const method = cachedMethods[methodName]
    let fetch_url = `${ws_url}?method=${method.name}`
    const http_method =
      $('#requestFormat').val() !== undefined
        ? $('#requestFormat').val()
        : 'GET'

    let fetch_params = {}
    if (http_method === 'post') {
      fetch_params.headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
      } // @TODO: send application/json but need to retrieve stream in WS

      fetch_params.body = method.params
        .filter((param) => {
          return $(
            "input.methodParameterSend[data-id='" + param.name + "']"
          ).is(':checked')
        })
        .map((param) => {
          const value = $(
            "input.methodParameterValue[data-id='" + param.name + "']"
          ).val()

          const splittedValue = value.split('|')
          if (param.acceptArray && splittedValue.length > 1) {
            return splittedValue
              .map(
                (value) => param.name + '[]' + '=' + encodeURIComponent(value)
              )
              .join('&')
          } else {
            return param.name + '=' + encodeURIComponent(value)
          }
        })
        .join('&')

      $('#requestDisplay')
        .show()
        .find('.url')
        .html(fetch_url)
        .end()
        .find('.params')
        .show()
        .html(JSON.stringify(fetch_params, null, 4))
    } else {
      const searchParams = new URLSearchParams()

      method.params
        .filter((param) => {
          return $(
            "input.methodParameterSend[data-id='" + param.name + "']"
          ).is(':checked')
        })
        .forEach((param) => {
          const value = $(
            "input.methodParameterValue[data-id='" + param.name + "']"
          ).val()

          const splittedValue = value.split('|')
          if (param.acceptArray && splittedValue.length > 1) {
            splittedValue.forEach((value) =>
              searchParams.append(param.name + '[]', value)
            )
          } else {
            searchParams.append(param.name, value)
          }
        })

      if (searchParams.size > 0) {
        fetch_url += '&' + searchParams
      }

      $('#requestDisplay')
        .show()
        .find('.url')
        .html(fetch_url)
        .end()
        .find('.params')
        .hide()
    }

    fetch_params.method = http_method
    fetch_params.credentials = 'same-origin'

    fetch(fetch_url, fetch_params)
      .then((response) => response.json())
      .then((response) => {
        $('#results')
          .show()
          .find('pre')
          .html(JSON.stringify(response, null, 2))
      })
      .catch((err) => displayError)
  }
})
