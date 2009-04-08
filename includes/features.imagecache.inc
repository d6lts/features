<?php
// $Id$

/**
 * Implementation of hook_features_export_options().
 */
function imagecache_features_export_options() {
  $options = array();
  foreach (imagecache_presets() as $preset) {
    $options[$preset['presetname']] = $preset['presetname'];
  }
  return $options;
}

/**
 * Implementation of hook_features_export().
 */
function imagecache_features_export($data, &$export, $module_name = '') {
  // Collect a module to preset map
  $map = array();
  $modules = module_implements('imagecache_default_presets');
  foreach ($modules as $module) {
    $presets = module_invoke($module, 'imagecache_default_presets');
    foreach ($presets as $preset) {
      $map[$preset['presetname']] = $module;
    }
  }
  foreach ($data as $preset) {
    // If another module provides this preset, add it as a dependency
    if (isset($map[$preset])) {
      $module = $map[$preset];
      $export['dependencies'][$module] = $module;
    }
    // Otherwise, export the preset
    else {
      $export['features']['imagecache'][$preset] = $preset;
    }
  }
}

/**
 * Implementation of hook_features_export_render().
 */
function imagecache_features_export_render($module_name, $data) {
  $omit = array('presetid', 'storage');

  $items = array();
  foreach ($data as $key) {
    $preset = imagecache_preset_by_name($key);
    foreach ($omit as $i) {
      unset($preset[$i]);
    }
    $items[$key] = $preset;
  }
  $code = "  \$items = ". features_var_export($items, '  ') .";\n";
  $code .= '  return $items;';
  return array('imagecache_default_presets' => $code);
}
