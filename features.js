// $Id$

Drupal.behaviors.featuresTable = function() {
  $('table.features input').bind('change', function() {
    if (!$(this).attr('checked')) {
      $(this).parents('tr').removeClass('enabled').addClass('disabled');
    }
    else {
      $(this).parents('tr').addClass('enabled').removeClass('disabled');
    }
  });
};

Drupal.behaviors.featuresMachineReadable = function() {
  $('.feature-name').after(' <small class="feature-filename-suffix">&nbsp;</small>');
  if ($('.feature-filename').val() == $('.feature-name').val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/_+/g, '_') || $('.feature-filename').val() == '') {
    $('.feature-filename').parents('.form-item').hide();
    $('.feature-name').keyup(function() {
      var machine = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/_+/g, '_');
      if (machine != '_' && machine != '') {
        $('.feature-filename').val(machine);
        $('.feature-filename-suffix').empty().append(' Machine name: ' + machine + ' [').append($('<a href="#">'+ Drupal.t('Edit') +'</a>').click(function() {
          $('.feature-filename').parents('.form-item').show();
          $('.feature-filename-suffix').hide();
          $('.feature-name').unbind('keyup');
          return false;
        })).append(']');
      }
      else {
        $('.feature-filename').val(machine);
        $('.feature-filename-suffix').text('');
      }
    });
    $('.feature-name').keyup();
  }
}
