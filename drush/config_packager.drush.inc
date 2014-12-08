<?php

/**
 * @file
 * Configuration Packager module drush integration.
 */

/**
 * Implements hook_drush_command().
 */
function config_packager_drush_command() {
  $items = array();

  $items['config-packager-list-packages'] = array(
    'description' => 'Display a list of all packages available to be generated. If a package name is provided as an argument, then all of the configuration objects assigned to that package will be listed.',
    'examples' => array(
      "drush config-packager-list-packages" => 'Display a list of all packages available to be generated.',
      "drush config-packager-list-packages 'article'" => "Display a list of all configuration objects assigned to the 'article' package.",
    ),
    'arguments' => array(
      'package' => 'The package to list. Optional; if specified, lists all configuration objects assigned to that package. If no package is specified, lists all of the packages available to be generated.',
    ),
    'options' => array(
    ),
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'list',
      'field-labels' => array(
        'machine_name' => 'Machine name',
        'name' => 'Name',
        'object' => 'Configuration object',
      ),
      'output-data-type' => 'format-table',
    ),
    'aliases' => array('cp-lp'),
  );

  return $items;
}

/**
 * Drush command callback for config-packager-list-packages.
 */
function drush_config_packager_list_packages($package = '') {
  $manager = \Drupal::service('config_packager.manager');
  $assigner = \Drupal::service('config_packager_assigner');
  $assigner->assignConfigPackages();
  $packages = $manager->getPackages();

  $result = array();

  // If no package was specified, list all packages.
  if (empty($package)) {
    drush_hide_output_fields(array('object'));
    foreach ($packages as $machine_name => $item) {
      $result[$machine_name] = array(
        'machine_name' => $machine_name,
        'name' => $item['name'],
      );
    }
  }
  // If a valid package was listed, list its configuration.
  elseif (isset($packages[$package]['config'])) {
    drush_hide_output_fields(array('machine_name', 'name'));
    foreach ($packages[$package]['config'] as $item_name) {
      $result[$item_name] = array(
        'object' => $item_name,
      );
    }
  }
  else {
    return FALSE;
  }

  return $result;
}
