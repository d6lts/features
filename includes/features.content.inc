<?php
// $Id$

/**
 * Implementation of hook_features_export().
 */
function content_features_export($data, &$export) {
  $pipe = array();
  // Default hooks are provided by the feature module so we need as a to add
  // it as a dependency.
  $export['dependencies']['features'] = 'features';
  foreach ($data as $instance) {
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

  return $pipe;
}

/**
 * Implementation of hook_features_export_render().
 */
function content_features_export_render($module = 'foo', $data) {
  $code = array();
  $code[] = '/**';
  $code[] = ' * Implementation of hook_content_default_fields().';
  $code[] = ' */';
  $code[] = "function {$module}_content_default_fields() {";
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
  $code[] = '}';
  return implode("\n", $code);
}
