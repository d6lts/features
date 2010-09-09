<?php
// $Id$

/**
 * Determine context major branch version.
 */
function context_features_get_version() {
  if (function_exists('context_ctools_plugin_api')) {
    $api = context_ctools_plugin_api('context', 'plugins');
    return $api['version'];
  }
  return 2;
}

/**
 * Implementation of hook_features_api().
 */
function context_features_api() {
  $api = array('context' => array());
  // 3.x
  if (context_features_get_version() === 3) {
    $api['context'] = array(
      'api' => 'context',
      'current_version' => 3,
      'module' => 'context',
      'default_file' => FEATURES_DEFAULTS_CUSTOM,
      'default_filename' => 'context',
    );
  }
  // 2.x
  $api['context'] += array(
    'name' => 'Contexts',
    'feature_source' => TRUE,
    'default_hook' => 'context_default_contexts'
  );
  return $api;
}

/**
 * Implementation of hook_features_export_options();
 */
function context_features_export_options() {
  // 3.x
  if (context_features_get_version() === 3) {
    return ctools_component_features_export_options('context');
  }
  // 2.x
  $contexts = context_enabled_contexts();
  $options = array();
  foreach ($contexts as $identifier => $context) {
    $options[$identifier] = "{$context->namespace} > {$context->attribute} > {$context->value}";
  }
  return $options;
}

/**
 * Implementation of hook_features_export().
 */
function context_features_export($data, &$export, $module_name = '') {
  $pipe = ctools_component_features_export('context', $data, $export, $module_name);

  $contexts = context_load();
  foreach ($data as $identifier) {
    if (isset($contexts[$identifier])) {
      $context = $contexts[$identifier];
      // Conditions.
      // Currently only node and views conditions are supported.
      // @TODO: Should this be delegated to a method on the plugin?
      foreach (array('node', 'views') as $key) {
        if (!empty($context->conditions{$key}['values'])) {
          foreach ($context->conditions{$key}['values'] as $item) {
            // Special pipe for views
            if ($key === 'views') {
              $split = explode(':', $item);
              $view_name = array_shift($split);
              $pipe[$key][$view_name] = $view_name;
            }
            else {
              $pipe[$key][$item] = $item;
            }
          }
        }
      }
      // Reactions.
      if (!empty($context->reactions['block']['blocks'])) {
        foreach ($context->reactions['block']['blocks'] as $block) {
          $block = (array) $block;
          $bid = "{$block['module']}-{$block['delta']}";
          $pipe['block'][$bid] = $bid;
        }
      }
    }
  }
  return $pipe;
}

/**
 * Implementation of hook_features_revert().
 *
 * @param $module
 * name of module to revert content for
 */
function context_features_revert($module = NULL) {
  $return = ctools_component_features_revert('context', $module);
  context_invalidate_cache();
  return $return;
}
