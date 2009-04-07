<?php
// $Id$

// Implementation of hook_features_export_options().
function imagecache_features_export_options() {
  $options = array();
  foreach (imagecache_presets() as $preset) {
    $options[$preset['presetname']] = $preset['presetname'];
  }
  return $options;
}

// Implementation of hook_features_export
function imagecache_features_export($data, &$export) {
  foreach ($data as $preset) {
    $export['features']['imagecache'][$preset] = $preset;
  }
}

// Implementation of hook_features_export_render.
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
