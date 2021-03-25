import jcrop from 'jcrop'

function from_coi(f, total) {
  return f * total
}

function to_coi(v, total) {
  return v / total
}

if ($('#jcrop').length > 0) {
  const stage = jcrop.attach('jcrop')
  if (phyxo !== undefined && phyxo.coi !== undefined) {
    var $img = $('#jcrop')
    const rect = jcrop.Rect.create(
      from_coi(phyxo.coi.l, $img.width()),
      from_coi(phyxo.coi.t, $img.height()),
      from_coi(phyxo.coi.r, $img.width()),
      from_coi(phyxo.coi.b, $img.height())
    )
    const options = {}
    stage.newWidget(rect, options)
  }

  stage.listen('crop.change', (widget, e) => {
    const pos = widget.pos

    var $img = $('#jcrop')
    $('#l').val(to_coi(pos.x, $img.width()))
    $('#t').val(to_coi(pos.y, $img.height()))
    $('#r').val(to_coi(pos.w, $img.width()))
    $('#b').val(to_coi(pos.h, $img.height()))
  })

  stage.listen('crop.remove', (widget, e) => {
    $('#l,#t,#r,#b').val('')
  })
}
