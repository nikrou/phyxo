$.flashMessage = function (source, data) {
  $(source).find('.message').remove()
  $(source).hide().append(data).fadeIn(500) //.fadeOut(3000);
}

$.fn.flashMessage = function (data) {
  this.each(function () {
    new $.flashMessage(this, `<div class="message">${data}</div>`)
  })
}

$(function () {
  function setPrivacyLevel(id, level, label) {
    const url = phyxo_root_url + 'ws?method=pwg.images.setPrivacyLevel'

    const fetch_params = {
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: new Headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ image_id: id, level: level }),
    }

    fetch(url, fetch_params)
      .then((response) => response.json())
      .then((json) => {
        $('#dropdownPermissions').html(label)
        $('.permission-li').removeClass('active')
        $('#permission-' + level).addClass('active')
      })
      .catch((err) => console.log(err))
  }

  function updateRating(image_id, rate) {
    const url = phyxo_root_url + 'ws?method=pwg.images.rate'

    const fetch_params = {
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: new Headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ image_id: image_id, rate: rate }),
    }

    fetch(url, fetch_params)
      .then((response) => response.json())
      .then((json) => json.result)
      .then((rating) => {
        $('#updateRate').html(phyxo_update_your_rating)
        $('#ratingScore').html(rating.score)
        let count_message = ''
        if (rating.count == 1) {
          count_message = phyxo_rating_1.replace('%d', rating.count)
        } else {
          count_message = phyxo_ratings.replace('%d', rating.count)
        }
        $('#ratingCount').html(' (' + count_message + ')')
      })
      .catch((err) => console.log(err))
  }

  $('#rateForm input[name="rating"]').change(function (e) {
    updateRating(phyxo_image_id, this.value)
  })

  $('[data-action="setPrivacyLevel"]').click(function (e) {
    setPrivacyLevel(
      $(this).data('id'),
      $(this).data('level'),
      $(this).data('label')
    )
  })

  $('[data-action="addToCaddie"]').click(async function (e) {
    e.preventDefault()

    const image_id = $(this).data('id')
    const url = `${phyxo_root_url}api/caddy/add`

    const fetch_params = {
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: new Headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ imageIds: [image_id] }),
    }

    try {
      const response = await fetch(url, fetch_params)

      if (response.ok) {
        const json = await response.json()
        $('.alert').addClass('alert-info').flashMessage(json)
      } else {
        if (response.status === 404) {
          throw new Error('404, Not found')
        }

        if (response.status === 500) {
          const json = await response.json()
          throw new Error('500, internal server error : ' + json.description)
        }

        throw new Error(response.status)
      }
    } catch (error) {
      $('.alert').addClass('alert-error').flashMessage(error)
    }
  })

  $('[data-action="addOrRemoveFavorite"]').on('click', function (e) {
    e.preventDefault()

    const tag_a = this
    const fetch_params = {
      method: 'GET',
      mode: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }

    fetch(tag_a.href, fetch_params)
      .then((response) => response.json())
      .then((response) => {
        tag_a.href = response.href
        tag_a.title = response.title
        const icon = $(tag_a).find('i.fa')
        if (icon.hasClass('fa-heart')) {
          icon.removeClass('fa-heart').addClass('fa-heart-o')
        } else {
          icon.removeClass('fa-heart-o').addClass('fa-heart')
        }
      })
  })
})
