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
  if ($('.feature-name').size() > 0) {
    $('.feature-name').after(' <small class="feature-module-name-suffix">&nbsp;</small>');
    if ($('.feature-module-name').val() == $('.feature-name').val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/_+/g, '_') || $('.feature-module-name').val() == '') {
      $('.feature-module-name').parents('.form-item').hide();
      $('.feature-name').keyup(function() {
        var machine = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/_+/g, '_');
        if (machine != '_' && machine != '') {
          $('.feature-module-name').val(machine);
          $('.feature-module-name-suffix').empty().append(' Machine name: ' + machine + ' [').append($('<a href="#">'+ Drupal.t('Edit') +'</a>').click(function() {
            $('.feature-module-name').parents('.form-item').show();
            $('.feature-module-name-suffix').hide();
            $('.feature-name').unbind('keyup');
            return false;
          })).append(']');
        }
        else {
          $('.feature-module-name').val(machine);
          $('.feature-module-name-suffix').text('');
        }
      });
      $('.feature-name').keyup();
    }
  }
}
