<?php
// $Id$
/**
 * Field export
 */
function field_features_api() {
  return array(
    'field' => array(
      'name' => t('Fields'),
      'default_hook' => 'field_default_fields',
    )
  );
}

function field_features_export($data, &$export, $module_name = '') {
  $pipe = array();

  // The field_default_fields() hook integration is provided by the
  // features module so we need to add it as a dependency.
  $export['dependencies']['features'] = 'features';
  
 // Collect a module to style map
  $map = array();
  $modules = module_implements('field_default_fields');
  foreach ($modules as $module) {
    $fields = module_invoke($module, 'field_default_fields');
    foreach ($fields as $name => $field) {
      $map["{$field['bundle']}-{$field['field_name']}"] = $module;
    }
  }


  foreach ($data as $instance) {
    list($bundle, $field) = explode('-', $instance);
    // If this field is already provided by another module, remove the field
    // and add the other module as a dependency.
    if (isset($map[$instance]) && $map[$instance] != $module_name) {
      if (isset($export['features']['field'][$instance])) {
        unset($export['features']['field'][$instance]);
      }
      $module = $map[$instance];
      $export['dependencies'][$module] = $module;
    }
    // If the field has not yet been exported, add it
    else {

      $export['features']['field'][$instance] = $instance;

      $field_info = field_info_field($field); 
      if ($field_info) {
        $export['dependencies'][$field_info['module']] = $field_info['module'];

        $storage_module = $field_info['storage']['module'];
        $export['dependencies'][$storage_module] = $storage_module;
      }

      $instance_info = field_info_instance('node', $field, $bundle);
      if ($instance_info) {
        $export['dependencies'][$instance_info['widget']['module']] = $instance_info['widget']['module'];
        foreach ($instance_info['display'] as $key => $display) {
          $export['dependencies'][$display['module']] = $display['module'];

          // todo : handle the pipe to image styles
        }
      }
    }
  }
}

/**
 * Implementation of hook_features_export_render().
 */
function field_features_export_render($module = 'foo', $data) {
  $translatables = $code = array();

  $code[] = '  $fields = array();';
  $code[] = '';
  foreach ($data as $instance) {
    list($bundle, $field_name) = explode('-', $instance);

    $field = field_info_field($field_name); 

    unset($field['columns']);
    unset($field['locked']);
    unset($field['storage']);

    $code[] = '  // Exported field: ' . $field_name;
    $code[] = '  $fields[] = ' . features_var_export($field, '  ') . ';';
    $code[] = '';

    // Add any labels to translatables array.
    if (!empty($field['widget']['label'])) {
      $translatables[] = $field['widget']['label'];
    }
  }
  if (!empty($translatables)) {
    $code[] = features_translatables_export($translatables, '  ');
  }
  $code[] = '  return $fields;';
  $code = implode("\n", $code);
  return array('field_default_fields' => $code);
}


/**
 * Implementation of hook_features_revert().
 */
function field_features_revert($module) {
  field_features_rebuild($module);
}

/**
 * Implementation of hook_features_rebuild().
 * Rebuilds CCK field definitions from code defaults.
 */
function field_features_rebuild($module) {
  $fields = module_invoke($module, 'field_default_fields');
  if (!empty($fields)) {
    field_info_cache_clear();

    foreach ($fields as $field) {
      $existing_field = field_info_field($field['field_name']);
      if ($existing_field) {
        field_update_field($field);
      }
      else {
        field_create_field($field);
      }

      $existing_instance = field_info_instance($field['entity_type'], $field['type_name'], $field['bundle']);
      if ($existing_instance) {
        field_update_instance($field);
      }
      else {
        field_create_instance($field);
      }
      variable_set('menu_rebuild_needed', TRUE);
    }
  }
}

