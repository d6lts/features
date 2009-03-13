<?php

/**
 * Display feature component info
 */
function template_preprocess_features_admin_components(&$vars) {
  drupal_add_css(drupal_get_path('module', 'features') .'/features.css');

  $rows = array();
  foreach ($vars['dependencies'] as $dependency => $status) {
    $row = array(
      array('data' => $dependency, 'class' => 'component'),
      theme('features_module_status', $status),
    );
    $rows[] = $row;
  }
  $vars['dependencies'] = theme('table', array(t('Dependency'), t('Status')), $rows);

  $rows = array();
  foreach ($vars['components'] as $type => $items) {
    $row = array(
      array('data' => $type, 'header' => TRUE),
      array('data' => $vars['conflicts'][$type], 'header' => TRUE),
    );
    $rows[] = $row;

    foreach ($items as $item) {
      $row = array(
        array('data' => $item, 'colspan' => 2, 'class' => 'component'),
      );
      $rows[] = $row;
    }
  }
  $components = theme('table', array(), $rows);
  $vars['components'] = $components;
}

/**
 * Themes a module status display.
 */
function theme_features_module_status($status, $module = NULL) {
  switch ($status) {
    case FEATURES_MODULE_ENABLED:
      $text = !empty($module) ? $module : t('Enabled');
      return "<span class='admin-enabled'>{$text}</span>";
    case FEATURES_MODULE_DISABLED:
      $text = !empty($module) ? $module : t('Disabled');
      return "<span class='admin-disabled'>{$text}</span>";
    case FEATURES_MODULE_MISSING:
      $text = !empty($module) ? $module : t('Missing');
      return "<span class='admin-missing'>{$text}</span>";
  }
}

/**
 * Themes a module status display.
 */
function theme_features_storage($storage) {
  switch ($storage) {
    case FEATURES_OVERRIDDEN:
      return "<span class='admin-overridden'>". t('Overridden') ."</span>";
    case FEATURES_DEFAULT:
      return "<span class='admin-default'>". t('Default') ."</span>";
  }
}

/**
 * Theme function for displaying form buttons
 */
function theme_features_form_buttons($element) {
  drupal_add_css(drupal_get_path('module', 'features') .'/features.css');

  $output = drupal_render($element);
  return "<div class='buttons clear-block'>{$output}</div>";
}
