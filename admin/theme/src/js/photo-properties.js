import 'selectize'
import './LocalStorageCache'

$(function () {
  if (
    phyxo === undefined ||
    phyxo.categoriesCache === undefined ||
    phyxo.tagsCache === undefined
  ) {
    return
  }

  const categoriesCache = new CategoriesCache(phyxo.categoriesCache)
  categoriesCache.selectize($('[data-selectize="categories"]'))

  const tagsCache = new TagsCache(phyxo.tagsCache)
  tagsCache.selectize($('[data-selectize="tags"]'), {
    lang: {
      Add: phyxo_msg.create,
    },
  })
})
