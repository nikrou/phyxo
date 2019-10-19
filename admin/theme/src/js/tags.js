import _ from 'underscore';

_.templateSettings.variable = 'tags';

$(function() {
  function highlighTags() {
    $('.checktag').each(function() {
      var parent = $(this).parent('div');
      if (
        $(this)
          .children('input[type=checkbox]')
          .is(':checked')
      ) {
        $(parent).addClass('selected');
      } else {
        $(parent).removeClass('selected');
      }
    });
  }

  $('.checktag').change(function() {
    highlighTags();
    displayAction();
  });

  $('#tagSelectAll').click(function() {
    $('.checktag input[type=checkbox]').prop('checked', true);

    highlighTags();
    displayAction();
    return false;
  });

  $('#tagSelectNone').click(function() {
    $('.checktag input[type=checkbox]').prop('checked', false);

    highlighTags();
    displayAction();
    return false;
  });

  $('#tagSelectInvert').click(function() {
    $('.checktag input[type=checkbox]').each(function() {
      $(this).prop('checked', !$(this).prop('checked'));
    });

    highlighTags();
    displayAction();
    return false;
  });

  function displayAction() {
    const checked = $('#tags input[type="checkbox"]:checked').length;

    if (checked === 0) {
      $('#actions .action').addClass('d-none');

      $('#applyAction, #selectAction').addClass('d-none');
      $('#selectAction select').val('');
    } else {
      $('#applyAction, #selectAction').removeClass('d-none');

      const selectedAction = $('#selectAction select').val();
      if (selectedAction !== '') {
        showOrUpdateTemplate(selectedAction);
      }
    }
  }

  function showOrUpdateTemplate(action) {
    const tags = $('#tags input[type="checkbox"]:checked').map(function() {
      return {
        id: $(this).val(),
        name: $(this).data('name')
      };
    });

    const tpl = _.template($(`script.${action}`).html());

    $(`#${action}Html`).html(tpl(tags));
  }

  $('#mergeHtml').on('click', 'input[type="radio"]', function() {
    $('#mergeHtml .text-danger').removeClass('d-none');
    $(this)
      .parent()
      .find('.text-danger')
      .addClass('d-none');
  });

  $('#selectAction select').change(function() {
    const action = $(this).val();
    $('#actions .action').addClass('d-none');
    $('#showConfirm input[type="checkbox"]').prop('checked', false);

    $('#action-' + action).removeClass('d-none');

    if (action !== 'delete') {
      showOrUpdateTemplate(action);
    }
  });
});
