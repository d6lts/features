<?php
// $Id$

/**
 * Implementation of hook_features_export().
 */
function content_features_export($data, &$export, $module_name = '') {
  $pipe = array();

  // The content_default_fields() hook integration is provided by the
  // features module so we need to add it as a dependency.
  $export['dependencies']['features'] = 'features';

  // Collect a field to module map
  $map = array();
  $modules = module_implements('content_default_fields');
  foreach ($modules as $module) {
    $fields = module_invoke($module, 'content_default_fields');
    foreach ($fields as $field) {
      $map["{$field['type_name']}-{$field['field_name']}"] = $module;
    }
  }

  foreach ($data as $instance) {
    // If this field is already provided by another module, remove the field
    // and add the other module as a dependency.
    if (isset($map[$instance]) && $map[$instance] != $module_name) {
      if (isset($export['features']['content'][$instance])) {
        unset($export['features']['content'][$instance]);
      }
      $module = $map[$instance];
      $export['dependencies'][$module] = $module;
    }
    // If the field has not yet been exported, add it
    else {
      $split = explode('-', $instance);
      $type_name = $split[0];
      $field_name = $split[1];
      $field = content_fields($field_name, $type_name);

      if ($field) {
        // Add field item instance for later export.
        $export['features']['content'][$instance] = $instance;

        // Add module which provides field.
        $export['dependencies'][$field['module']] = $field['module'];

        // Add module which provides field widget.
        $export['dependencies'][$field['widget']['module']] = $field['widget']['module'];

        // Add modules which provide display.
        foreach (array('teaser', 'full') as $display) {
          $formatter = _content_get_formatter($field['display_settings'][$display]['format'], $field['type']);
          $export['dependencies'][$formatter['module']] = $formatter['module'];

          // TODO make this logic more generic, for now though we just handle
          // the imagecache presets.
          if ($formatter['module'] == 'imagecache') {
            $format = $field['display_settings'][$display]['format'];

            $parts = explode('_', $format);
            $style = array_pop($parts);
            $presetname = implode('_', $parts);

            $pipe[$formatter['module']][] = $presetname;
          }
        }
      }
    }
  }

  return $pipe;
}

/**
 * Implementation of hook_features_export_render().
 */
function content_features_export_render($module = 'foo', $data) {
  $code = array();
  $code[] = '  $fields = array();';
  $code[] = '';
  foreach ($data as $instance) {
    $instance = explode('-', $instance);
    $type_name = $instance[0];
    $field_name = $instance[1];

    $field = content_fields($field_name, $type_name);
    unset($field['columns']);
    unset($field['locked']);
    unset($field['db_storage']);

    $code[] = '  // Exported field: '. $field_name;
    $code[] = '  $fields[] = '. features_var_export($field, '  ') .';';
    $code[] = '';
  }
  $code[] = '  return $fields;';
  $code = implode("\n", $code);
  return array('content_default_fields' => $code);
}

/**
 * Implementation of hook_features_revert().
 *
 * @param $module
 * name of module to revert content for
 */
function content_features_revert($module = NULL) {
  // Get all fields defined in this feature
  if (module_hook($module, 'content_default_fields')) {
    module_load_include('inc', 'content', 'includes/content.crud');
    $default_fields = module_invoke($module, 'content_default_fields');

    // update all fields to their default values
    foreach($default_fields as $field) {
      content_field_instance_update($field);
    }
  }
  else {
    drupal_set_message(t('Could not load default fields.'), 'error');
    return FALSE;
  }
  return TRUE;
}
