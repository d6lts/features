<?php

/**
 * Implements hook_content_default_fields().
 */
function features_test_content_default_fields() {
  module_load_include('inc', 'features_test', 'features_test.defaults');
  $args = func_get_args();
  return call_user_func_array('_features_test_content_default_fields', $args);
}

/**
 * Implements hook_fieldgroup_default_groups().
 */
function features_test_fieldgroup_default_groups() {
  module_load_include('inc', 'features_test', 'features_test.defaults');
  $args = func_get_args();
  return call_user_func_array('_features_test_fieldgroup_default_groups', $args);
}

/**
 * Implements hook_filter_default_formats().
 */
function features_test_filter_default_formats() {
  module_load_include('inc', 'features_test', 'features_test.defaults');
  $args = func_get_args();
  return call_user_func_array('_features_test_filter_default_formats', $args);
}

/**
 * Implements hook_imagecache_default_presets().
 */
function features_test_imagecache_default_presets() {
  module_load_include('inc', 'features_test', 'features_test.defaults');
  $args = func_get_args();
  return call_user_func_array('_features_test_imagecache_default_presets', $args);
}

/**
 * Implements hook_node_info().
 */
function features_test_node_info() {
  // TODO The array elements in this hook function need to be changed.
  module_load_include('inc', 'features_test', 'features_test.features.node');
  $args = func_get_args();
  return call_user_func_array('_features_test_node_info', $args);
}

/**
 * Implements hook_user_default_permissions().
 */
function features_test_user_default_permissions() {
  module_load_include('inc', 'features_test', 'features_test.defaults');
  $args = func_get_args();
  return call_user_func_array('_features_test_user_default_permissions', $args);
}

/**
 * Implements hook_views_default_views().
 */
function features_test_views_default_views() {
  module_load_include('inc', 'features_test', 'features_test.features.views');
  $args = func_get_args();
  return call_user_func_array('_features_test_views_default_views', $args);
}
