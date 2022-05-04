document.onkeydown = function (e) {
  if ($('.pswp.pswp--open').length > 0) {
    return
  }

  const keyToRel = []
  keyToRel['End'] = { rel: 'last', ctrlKey: true }
  keyToRel['Home'] = { rel: 'first', ctrlKey: true }
  keyToRel['ArrowLeft'] = { rel: 'prev', ctrlKey: false }
  keyToRel['ArrowUp'] = { rel: 'up', ctrlKey: true }
  keyToRel['ArrowRight'] = { rel: 'next', ctrlKey: false }

  const keyCode = e.key

  if (keyCode && keyToRel[keyCode] !== undefined) {
    if (keyToRel[keyCode]['ctrlKey'] && !e.ctrlKey) {
      return
    }
    const link = $('link[rel="' + keyToRel[keyCode]['rel'] + '"]')
    if (link.length) {
      document.location = link.attr('href')
    }
  }
}
