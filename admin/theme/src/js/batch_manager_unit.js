import 'selectize'
import './LocalStorageCache'

$(function () {
  if (phyxo === undefined || phyxo.tagsCache === undefined) {
    return
  }

  const tagsCache = new TagsCache(phyxo.tagsCache)
  tagsCache.selectize($('[data-selectize="tags"]'), {
    lang: {
      Add: phyxo_msg.create,
    },
  })
})
