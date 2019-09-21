import { updateExtension, updateIgnore, performAction } from './api';

$(function() {
  $('.extensions .install').on('click', e =>
    updateExtension(
      $(e.target).data('type'),
      $(e.target).data('ext-id'),
      $(e.target).data('revision-id')
    )
      .then(data => {
        if ($(e.target).data('redirect')) {
          window.location = $(e.target).data('redirect');
        }
      })
      .catch(err => console.error(err))
  );

  $('.extensions .ignore').on('click', e =>
    updateIgnore({
      extensionType: extType,
      extensionId: $(e.target).data('ext-id')
    })
      .then(() => {
        const $countTag = $('#resetIgnored').find('.count');
        const currentCount = parseInt($countTag.html(), 10);
        $countTag.html(currentCount + 1);
        $('#plugin_' + $(e.target).data('ext-id')).addClass('ignore');
        if (currentCount === 0) {
          $('#resetIgnored').show();
        }

        if ($('.extensions .extension:visible').length == 0) {
          $('#up-to-date').show();
          $('#updateAll, #ignoreAll, .extensions h3').hide();
        }
      })
      .catch(err => console.error(err))
  );

  $('.extensions .activate').on('click', e =>
    performAction($(e.target).data('ext-id'), 'activate')
      .then(() => window.location.reload())
      .catch(err => console.error(err))
  );

  $('.extensions .deactivate').on('click', e =>
    performAction($(e.target).data('ext-id'), 'deactivate')
      .then(() => window.location.reload())
      .catch(err => console.error(err))
  );

  $('.extensions .delete').on('click', e => {
    if (!confirm(confirmMsg)) {
      return;
    }

    performAction($(e.target).data('ext-id'), 'delete')
      .then(() => $('#plugin-' + $(e.target).data('ext-id')).remove())
      .catch(err => console.error(err));
  });

  $('.extensions .uninstall').on('click', e => {
    if (!confirm(confirmMsg)) {
      return;
    }

    performAction($(e.target).data('ext-id'), 'uninstall')
      .then(() => window.location.reload())
      .catch(err => console.error(err));
  });

  $('.extensions .restore').on('click', e => {
    if (!confirm(confirmMsg)) {
      return;
    }

    performAction($(e.target).data('ext-id'), 'restore')
      .then(() => window.location.reload())
      .catch(err => console.error(err));
  });

  $('#resetIgnored').on('click', () =>
    updateIgnore({ extensionType: extType, reset: true })
      .then(() => window.location.reload())
      .catch(err => console.error(err))
  );

  $('#updateAll').on('click', () => {
    // @TODO : add a queue manager
    $('.extensions .extension:not(.ignore) .install').trigger('click');
  });

  $('#ignoreAll').on('click', () => {
    // @TODO : add a queue manager
    $('.extensions .extension:not(.ignore) .ignore').trigger('click');
  });

  $('#deactivateAll').on('click', () => {
    // @TODO : add a queue manager
    console.log(
      'to deactivate',
      $('.extensions.state-active .deactivate').length
    );
    $('.extensions.state-active .deactivate').trigger('click');
  });
});
