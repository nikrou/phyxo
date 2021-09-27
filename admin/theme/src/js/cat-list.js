import 'jquery-ui/ui/widgets/sortable'

$(function () {
  if ($('.albums').length > 0) {
    $('.albums').sortable({
      axis: 'y',
      opacity: 0.8,
      update: function () {
        $('#manualOrder').show()
      },
    })

    $('#categoryOrdering').submit(function () {
      const ar = $('.albums').sortable('toArray')
      for (let i = 0; i < ar.length; i++) {
        let cat = ar[i].split('album-')
        document.getElementsByName('catOrd[' + cat[1] + ']')[0].value = i
      }
    })
  }
})
