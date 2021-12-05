import jcrop from 'jcrop'

function from_coi(f, total) {
  return f * total
}

function to_coi(v, total) {
  return v / total
}

function new_widget(stage, width, height) {
  const rect = jcrop.Rect.create(
    from_coi(phyxo.coi.l, width),
    from_coi(phyxo.coi.t, height),
    from_coi(phyxo.coi.r, width),
    from_coi(phyxo.coi.b, height)
  )
  stage.newWidget(rect, {})
}

$(function () {
  if (phyxo === undefined || phyxo.coi === undefined) {
    return
  }

  const $img = $('#jcrop')

  if ($img.length > 0) {
    const stage = jcrop.attach('jcrop')

    $img.on('load', function () {
      new_widget(stage, this.width, this.height)
    })

    new_widget(stage, $img.width(), $img.height())

    stage.listen('crop.change', (widget, e) => {
      const pos = widget.pos
      $('#l').val(to_coi(pos.x, $img.width()))
      $('#t').val(to_coi(pos.y, $img.height()))
      $('#r').val(to_coi(pos.w, $img.width()))
      $('#b').val(to_coi(pos.h, $img.height()))
    })

    stage.listen('crop.remove', (widget, e) => {
      $('#l,#t,#r,#b').val('')
    })
  }
})
