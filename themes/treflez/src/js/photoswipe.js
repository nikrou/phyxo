import '../scss/theme/_photoswipe.scss'

import PhotoSwipeLightbox from 'photoswipe/lightbox'
import PhotoSwipe from 'photoswipe'
import PhotoSwipeDynamicCaption from 'photoswipe-dynamic-caption-plugin'

if (window.phyxo_photoswipe_config !== undefined) {
  const lightbox = new PhotoSwipeLightbox({
    children: '.thumbnail',
    pswpModule: () => PhotoSwipe,
    ...phyxo_photoswipe_config,
  })

  const captionPlugin = new PhotoSwipeDynamicCaption(lightbox, {
    type: 'auto',
    captionContent: '.photoswipe-caption-content',
  })
  lightbox.init()

  $('#startPhotoSwipe').on('click', function () {
    document.querySelector(`${phyxo_photoswipe_config.gallery} a`).click()
  })
}
