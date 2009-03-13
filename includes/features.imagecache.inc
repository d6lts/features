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
    $export['items']['imagecache'][$preset] = $preset;
  }
}

// Implementation of hook_features_export_render.
function imagecache_features_export_render($module_name, $data) {
  $omit = array('presetid', 'storage');

  foreach ($data as $key => $value) {
    $preset = imagecache_preset_by_name($key);
    foreach ($omit as $i) {
      unset($preset[$i]);
    }
    $code .= "\"$key\" => ". var_export($preset, true) . ",";
  }

  return <<<EOT
// Implementation of hook_imagecache_default_presets().
function {$module_name}_imagecache_default_presets() {
  return array({$code}
  );
}
EOT;
}
