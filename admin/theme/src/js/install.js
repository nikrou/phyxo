import '../scss/style.scss'

const ready = (callback) => {
  if (document.readyState != 'loading') {
    callback()
  } else {
    document.addEventListener('DOMContentLoaded', callback)
  }
}

ready(() => {
  const dblayer = document.getElementById('dblayer')

  if (!dblayer) {
    return
  }

  if (dblayer.value === 'sqlite') {
    document
      .querySelectorAll('.no-sqlite')
      .forEach((element) => (element.style.display = 'none'))
  }

  dblayer.addEventListener('change', (element) => {
    document
      .querySelectorAll('.no-sqlite')
      .forEach((element) => (element.style.display = 'block'))

    if (element.target.value === 'sqlite') {
      document
        .querySelectorAll('.no-sqlite')
        .forEach((element) => (element.style.display = 'none'))
    }
  })
})
