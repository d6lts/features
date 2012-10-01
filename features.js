(function ($) {
  Drupal.behaviors.features = {
    attach: function(context, settings) {
      // Features management form
      $('table.features:not(.processed)', context).each(function() {
        $(this).addClass('processed');

        // Check the overridden status of each feature
        Drupal.features.checkStatus();

        // Add some nicer row hilighting when checkboxes change values
        $('input', this).bind('change', function() {
          if (!$(this).attr('checked')) {
            $(this).parents('tr').removeClass('enabled').addClass('disabled');
          }
          else {
            $(this).parents('tr').addClass('enabled').removeClass('disabled');
          }
        });
      });

      // Export form component selector
      $('form.features-export-form select.features-select-components:not(.processed)', context).each(function() {
        $(this)
          .addClass('processed')
          .change(function() {
            var target = $(this).val();
            $('div.features-select').hide();
            $('div.features-select-' + target).show();
            return false;
        }).trigger('change');
      });

      // Export form machine-readable JS
      $('.feature-name:not(.processed)', context).each(function() {
        $('.feature-name')
          .addClass('processed')
          .after(' <small class="feature-module-name-suffix">&nbsp;</small>');
        if ($('.feature-module-name').val() === $('.feature-name').val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/_+/g, '_') || $('.feature-module-name').val() === '') {
          $('.feature-module-name').parents('.form-item').hide();
          $('.feature-name').bind('keyup change', function() {
            var machine = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/_+/g, '_');
            if (machine !== '_' && machine !== '') {
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
      });

      // provide timer for auto-refresh trigger
      var timeoutID = 0;
      function _triggerTimeout() {
        if ($('#edit-auto-refresh').is(':checked')) {
          $('input.features-refresh-button').trigger('refresh_components');
        }
      }
      function _resetTimeout() {
        if (timeoutID != 0) {
          window.clearTimeout(timeoutID);
        }
        timeoutID = window.setTimeout(_triggerTimeout, 2000);
      }

      // Handle component selection UI
      $('.component-select input[type=checkbox]', context).click(function() {
        _resetTimeout();
        var curItem = $(this).parents('.form-type-checkbox');
        if ($(this).is(':checked')) {
          var newParent = $(this).parents('.features-export-parent').find('.component-list .form-checkboxes.component-added');
          $(curItem).detach();
          $(curItem).appendTo(newParent);
          $(curItem).removeClass('component-select');
          $(curItem).addClass('component-added');
          $(newParent).parent().removeClass('features-export-empty');
        }
        else {
          var newParent = $(this).parents('.features-export-parent').find('.features-export-component .form-checkboxes.component-select');
          $(curItem).detach();
          $(curItem).appendTo(newParent);
          $(curItem).removeClass('component-added');
          $(curItem).addClass('component-select');
        }
      });
      $('.component-included input[type=checkbox]', context).click(function() {
        _resetTimeout();
        var curItem = $(this).parents('.form-type-checkbox').children('label');
        if ($(this).is(':checked')) {
          $(curItem).removeClass('component-added');
          $(curItem).addClass('component-included');
        }
        else {
          $(curItem).removeClass('component-included');
          $(curItem).addClass('component-added');
        }
      });
      $('.component-added input[type=checkbox]', context).click(function() {
        _resetTimeout();
      });
      $('.component-detected input[type=checkbox]', context).click(function() {
        _resetTimeout();
      });
      $('#edit-auto-refresh', context).click(function() {
        _resetTimeout();
      });
      if ($('#features-export-wrapper div.description').length > 0) {
        // annoying Rubik theme changes the class name from fieldset-description to description
        $('input.features-refresh-button').parent().insertBefore('#features-export-wrapper div.description');
      }
      else {
        $('input.features-refresh-button').parent().insertBefore('#features-export-wrapper div.fieldset-description');
      }
    }
  }


  Drupal.features = {
    'checkStatus': function() {
      $('table.features tbody tr').not('.processed').filter(':first').each(function() {
        var elem = $(this);
        $(elem).addClass('processed');
        var uri = $(this).find('a.admin-check').attr('href');
        if (uri) {
          $.get(uri, [], function(data) {
            $(elem).find('.admin-loading').hide();
            switch (data.storage) {
              case 3:
                $(elem).find('.admin-rebuilding').show();
                break;
              case 2:
                $(elem).find('.admin-needs-review').show();
                break;
              case 1:
                $(elem).find('.admin-overridden').show();
                break;
              default:
                $(elem).find('.admin-default').show();
                break;
            }
            Drupal.features.checkStatus();
          }, 'json');
        }
        else {
            Drupal.features.checkStatus();
          }
      });
    }
  };


})(jQuery);


