<?php

/**
 * @file
 * Features module drush integration.
 */

use Drupal\features\FeaturesManagerInterface;

/**
 * Implements hook_drush_command().
 */
function features_drush_command() {
  $items = array();

  $items['features-status'] = array(
    'description' => 'Display current Features settings.',
    'aliases' => array('fs'),
  );

  $items['features-list-packages'] = array(
    'description' => 'Display a list of all existing features and packages available to be generated.  If a package name is provided as an argument, then all of the configuration objects assigned to that package will be listed.',
    'examples' => array(
      "drush features-list-packages" => 'Display a list of all existing featurea and packages available to be generated.',
      "drush features-list-packages 'example_article'" => "Display a list of all configuration objects assigned to the 'example_article' package.",
    ),
    'arguments' => array(
      'package' => 'The package to list. Optional; if specified, lists all configuration objects assigned to that package. If no package is specified, lists all of the features.',
    ),
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'list',
      'field-labels' => array(
        'name' => 'Name',
        'machine_name' => 'Machine name',
        'status' => 'Status',
        'version' => 'Version',
        'state' => 'State',
        'object' => 'Configuration object',
      ),
      'output-data-type' => 'format-table',
    ),
    'aliases' => array('fl'),
  );

  $items['features-export'] = array(
    'description' => "Export the configuration on your site into a custom module.",
    'arguments' => array(
      'package' => 'A space delimited list of features to export.',
    ),
    'options' => array(
      'add-profile' => 'Package configuration modules in an install profile.',
    ),
    'examples' => array(
      "drush features-export" => 'Export all available packages.',
      "drush features-export example_article example_page" => "Export the example_article and example_page packages.",
      "drush features-export --add-profile" => "Export all available packages and add them to an install profile.",
    ),
    // add previous "fu" alias for compatibility
    'aliases' => array('fex', 'fu'),
  );

  return $items;
}

/**
 * Drush command callback for features-status.
 */
function drush_features_status() {
  $manager = \Drupal::service('features.manager');
  $assigner = \Drupal::service('features_assigner');
  $profile = $manager->getProfile();
  $methods = $assigner->getEnabledAssigners();
  drush_print(dt('Profile name: !profile', array('!profile' => $profile['machine_name'])));
  $dt_args = array('!methods' => implode(', ', array_keys($methods)));
  drush_print(dt('The following assignment methods are enabled:'));
  drush_print(dt('  !methods', $dt_args));
}

/**
 * Drush command callback for features-list-packages.
 */
function drush_features_list_packages($package = '') {
  $manager = \Drupal::service('features.manager');
  $assigner = \Drupal::service('features_assigner');
  $assigner->assignConfigPackages();
  $packages = $manager->getPackages();
  $profile = $manager->getProfile();

  $result = array();

  // If no package was specified, list all packages.
  if (empty($package)) {
    drush_hide_output_fields(array('object'));
    foreach ($packages as $item) {
      $result[$item['machine_name']] = array(
        'name' => $item['name'],
        'machine_name' => $item['machine_name'],
        'status' => $manager->statusLabel($item['status']),
        'version' => $item['version'],
        'state' => ($item['state'] != FeaturesManagerInterface::STATE_DEFAULT)
          ? $manager->stateLabel($item['state'])
          : '',
      );
    }
    return $result;
  }
  // If a valid package was listed, list its configuration.
  else {
    foreach ($packages as $item) {
      if ($item['machine_name'] == $package) {
        drush_hide_output_fields(array('machine_name', 'name', 'status', 'version', 'state'));
        foreach ($item['config'] as $item_name) {
          $result[$item_name] = array(
            'object' => $item_name,
          );
        }
        return $result;
      }
    }

  }

  // If no matching package found, return an error.
  drush_log(dt('Package "@package" not found.', array('@package' => $package)), 'warning');
  return FALSE;
}

/**
 * Drush command callback for features-export.
 */
function drush_features_export($packages = NULL) {
  $packages = func_get_args();

  $manager = \Drupal::service('features.manager');
  $assigner = \Drupal::service('features_assigner');
  $generator = \Drupal::service('features_generator');
  $assigner->assignConfigPackages();

  $add_profile = drush_get_option('add-profile');

  $all_packages = $manager->getPackages();
  foreach ($packages as $name) {
    if (!isset($all_packages[$name])) {
      return drush_set_error('', dt("The package @name does not exist.", array('@name' => $name)));
    }
  }

  if (empty($packages)) {
    $packages = array_keys($all_packages);
    $dt_args = array('!modules' => implode(', ', $packages));
    drush_print(dt('The following modules will be updated: !modules', $dt_args));
    if (!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort('Aborting.');
    }
  }

  // If any packages exist, confirm before overwriting.
  if ($existing_packages = $manager->listPackageDirectories($packages, $add_profile)) {
    foreach ($existing_packages as $name => $directory) {
      drush_print(dt("The extension @name already exists at !directory.", array('@name' => $name, '!directory' => $directory)));
    }
    // Apparently format_plural is not always available.
    if (count($existing_packages) == 1) {
      $message = dt('Would you like to overwrite it?');
    }
    else {
      $message = dt('Would you like to overwrite them?');
    }
    if (!drush_confirm($message)) {
      return drush_user_abort();
    }
  }

  // Use the write generation method.
  $method_id = \Drupal\features\Plugin\FeaturesGeneration\FeaturesGenerationWrite::METHOD_ID;

  if ($add_profile) {
    $result = $generator->generateProfile($method_id, $packages, FALSE);
  }
  else {
    $result = $generator->generatePackages($method_id, $packages, FALSE);
  }
  foreach ($result as $message) {
    $type = $message['success'] ? 'success' : 'error';
    drush_log($message['message'], $message['variables'], $type);
  }
}
