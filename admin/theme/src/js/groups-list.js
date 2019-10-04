$(function() {
  $('[id^=action_]').hide();
  $('.grp-action').hide();

  $('input.group_selection').click(function() {
    const nbSelected = $('input.group_selection').filter(':checked').length;

    if (nbSelected === 0) {
      $('#permitAction').addClass('d-none');
      $('#forbidAction').removeClass('d-none');
    } else {
      $('#permitAction').removeClass('d-none');
      $('#forbidAction').addClass('d-none');
    }

    $('p[data-group_id=' + $(this).prop('value') + ']').each(function() {
      $(this).toggleClass('d-none');
    });

    if (nbSelected < 2) {
      $('#two_to_select').show();
      $('#two_atleast').hide();
    } else {
      $('#two_to_select').hide();
      $('#two_atleast').show();
    }
  });

  $('select[name=selectAction]').change(function() {
    const divAction = $('#action_' + $(this).prop('value'));
    $('form[name="groups-action"]').attr('action', divAction.data('action'));
    $('[id^=action_]').hide();

    divAction.show();
    if ($(this).val() !== -1) {
      $('#applyActionBlock').removeClass('d-none');
    } else {
      $('#applyActionBlock').addClass('d-none');
    }
  });
});
