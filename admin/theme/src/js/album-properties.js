import 'selectize'
import './LocalStorageCache'

$(function () {
  if (phyxo === undefined || phyxo.categoriesCache === undefined) {
    return
  }

  const categoriesCache = new CategoriesCache(phyxo.categoriesCache)
  categoriesCache.selectize($('[data-selectize="parent-categories"]'), {
    default: 0,
    filter: function (categories, options) {
      const filtered = categories.filter(
        (category) => !/\b`${phyxo.cat_id}`\b/.test(category.uppercats)
      )

      filtered.push({
        id: 0,
        fullname: '------------',
        global_rank: 0,
      })

      return filtered
    },
  })
})
