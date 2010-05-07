<?php
// $Id$

/**
 * Implementation of hook_features_api().
 */
function node_features_api() {
  return array(
    'node' => array(
      'name' => t('Content types'),
      'feature_source' => TRUE,
      'default_hook' => 'node_info',
      'default_file' => FEATURES_DEFAULTS_INCLUDED,
    ),
  );
}

/**
 * Implementation of hook_features_export_options().
 */
function node_features_export_options() {
  return node_type_get_names();
}

/**
 * Implementation of hook_features_export.
 */
function node_features_export($data, &$export, $module_name = '') {
  $pipe = array();

  $type_map = node_type_get_types();

  $module_map = array();

  $modules = module_implements('node_info');
  foreach ($modules as $module) {
    $callback = $module . "_node_info";
    $node_types = call_user_func($callback);
    if (is_array($node_types)) {
      foreach ($node_types as $module_type => $module_info) {
        $module_map[$module_type] = $module;
      }
    }
  }



  foreach ($data as $type)  {
    // Poll node module to determine who provides the node type.
    if ($info = $type_map[$type]->base) {
      if (in_array($info, array('node_content', 'features'))) {
        $export['dependencies']['node'] = 'node';
        $export['dependencies']['features'] = 'features';
        $export['features']['node'][$type] = $type;
      }

      // If this node type is provided by a different module, add it as a dependency
      $module = $module_map[$type];
      if ($module && $module != $module_name) {
        $export['dependencies'][$module] = $module;
        unset($export['features']['node'][$type]);
      }

      $fields = field_info_instances('node', $type);
      foreach ($fields as $name => $field) {
        $pipe['field'][] = "{$field['bundle']}-{$field['field_name']}";
      }
	 	
    }
  }


  return $pipe;
}

/**
 * Implementation of hook_features_export_render().
 */
function node_features_export_render($module = 'foo', $data) {
  $elements = array(
    'name' => TRUE,
    'base' => FALSE,
    'description' => TRUE,
    'has_title' => FALSE,
    'title_label' => TRUE,
    'has_body' => FALSE,
    'body_label' => TRUE,
    'help' => TRUE,
  );
  $output = array();
  $output[] = '  $items = array(';
  foreach ($data as $type) {
    $info = node_type_get_type($type);

    // Force module name to be 'features' if set to 'node. If we leave as
    // 'node' the content type will be assumed to be database-stored by
    // the node module.
    $info->base = ($info->base === 'node') ? 'features' : $info->base;

    $output[] = "    '{$type}' => array(";
    foreach ($elements as $key => $t) {
      if ($t) {
        $text = str_replace("'", "\'", $info->$key);
        $text = !empty($text) ? "t('{$text}')" : "''";
        $output[] = "      '{$key}' => {$text},";
      }
      else {
        $output[] = "      '{$key}' => '{$info->$key}',";
      }
    }
    $output[] = "    ),";
  }
  $output[] = '  );';
  $output[] = '  return $items;';
  $output = implode("\n", $output);
  return array('node_info' => $output);
}

/**
 * Implementation of hook_features_revert().
 *
 * @param $module
 * name of module to revert content for
 */
function node_features_revert($module = NULL) {
  // Get default node types
  if (module_hook($module, 'node_info')) {
    $default_types = module_invoke($module, 'node_info');

    // Delete node types
    // We don't use node_type_delete() because we do not actually
    // want to delete the node type (and invoke hook_node_type()).
    // This can lead to bad consequences like CCK deleting field
    // storage in the DB.
    foreach ($default_types as $type_name => $type_info) {
      db_delete('node_type')
    ->condition('type', $type_name)
  ->execute();
    }
  }
  else {
    drupal_set_message(t('Could not load default node types.'), 'error');
    // drush_print(dt('Could not load default node types.'));
    return FALSE;
  }
  node_types_rebuild();
  menu_rebuild();
  return TRUE;
}
