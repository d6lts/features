<?php
// $Id$

/**
 * Implementation of hook_features_api().
 */
function image_features_api() {
  return array(
    'image' => array(
      'name' => t('Image styles'),
      'feature_source' => TRUE,
      'default_hook' => 'image_default_styles',
    )
  );
}

/**
 * Implementation of hook_features_export_options().
 */
function image_features_export_options() {
  $options = array();
  foreach (image_styles() as $name => $style) {
    $options[$name] = $style['name'];
  }
  return $options;
}

/**
 * Implementation of hook_features_export().
 */
function image_features_export($data, &$export, $module_name = '') {
  // Collect a module to style map
  $map = array();
  $modules = module_implements('image_default_styles');
  foreach ($modules as $module) {
    $styles = module_invoke($module, 'image_default_styles');
    foreach ($styles as $name => $style) {
      $map[$style['name']] = $module;
    }
  }
  foreach ($data as $style) {
    $export['dependencies']['image'] = 'image';
    // If another module provides this style, add it as a dependency
    if (isset($map[$style]) && $map[$style] != $module_name) {
      $module = $map[$style];
      $export['dependencies'][$module] = $module;
    }
    // Otherwise, export the style
    else {
      $export['features']['image'][$style] = $style;
    }
  }
}

/**
 * Implementation of hook_features_export_render().
 */
function image_features_export_render($module_name, $data) {
  $items = array();
  foreach ($data as $key) {
    $style = image_style_load($key);
    _image_features_style_sanitize($style);
    $items[$key] = $style;
  }
  $code = "  \$items = " . features_var_export($items, '  ') . ";\n";
  $code .= '  return $items;';
  return array('image_default_styles' => $code);
}

/**
 * Implementation of hook_features_revert().
 */
function image_features_revert($module) {
  $default_styles = module_invoke($module, 'image_default_styles');
  if (!empty($default_styles)) {
    foreach (array_keys($default_styles) as $default_style) {
      $style = image_style_by_name($default_style);
      if ($style) {
        image_style_delete($style);
      }
    }
  }
}

/**
 * Remove unnecessary keys for export.
 */
function _image_features_style_sanitize(&$style) {
  $omit = array('styleid', 'storage', 'actionid');
  if (is_array($style)) {
    foreach ($style as $k => $v) {
      if (in_array($k, $omit, TRUE)) {
        unset($style[$k]);
      }
      else if (is_array($v)) {
        _image_features_style_sanitize($style[$k]);
      }
    }
  }
}
