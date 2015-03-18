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
    'aliases' => array('fex', 'fu', 'fua', 'fu-all'),
  );

  $items['features-add'] = array(
    'description' => "Add a config item to a feature package.",
    'arguments' => array(
      'feature' => 'Feature package to export and add config to.',
      'components' => 'Patterns of config to add, see features-components for the format of patterns.',
    ),
    'aliases' => array('fa', 'fe'),
  );

  $items['features-components'] = array(
    'description' => 'List features components.',
    'arguments' => array(
      'patterns' => 'The features components type to list. Omit this argument to list all components.',
    ),
    'options' => array(
      'exported' => array(
        'description' => 'Show only components that have been exported.',
      ),
      'not-exported' => array(
        'description' => 'Show only components that have not been exported.',
      ),
    ),
    'aliases' => array('fc'),
  );

  return $items;
}

/**
 * Drush command callback for features-status.
 */
function drush_features_status() {
  $args = func_get_args();

  $manager = \Drupal::service('features.manager');
  $assigner = \Drupal::service('features_assigner');
  $profile = $manager->getProfile();
  $exportSettings = $manager->getExportSettings();
  $methods = $assigner->getEnabledAssigners();
  if (empty($profile['machine_name'])) {
    drush_print(dt('Export namespace: none'));
  }
  else {
    drush_print(dt('Export namespace: !name (!machine_name)',
      array(
        '!name' => $profile['name'],
        '!machine_name' => $profile['machine_name']
      )));
  }
  drush_print(dt('Export folder: !folder', array('!folder' => $exportSettings['folder'])));
  $dt_args = array('!methods' => implode(', ', array_keys($methods)));
  drush_print(dt('The following assignment methods are enabled:'));
  drush_print(dt('  !methods', $dt_args));

  if (!empty($args)) {
    $config = $manager->getFullConfigCollection();
    if (count($args) > 1) {
      print_r(array_keys($config));
    }
    else {
      print_r($config[$args[0]]);
    }
  }
}

/**
 * Drush command callback for features-list-packages.
 */
function drush_features_list_packages($package = '') {
  $manager = \Drupal::service('features.manager');
  $assigner = \Drupal::service('features_assigner');
  $assigner->assignConfigPackages();
  $packages = $manager->getPackages();

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
  // this is going to filter based on current namespace
  // so might need to clear the namespace before calling to export
  // packages outside the current namespace
  $assigner->assignConfigPackages();

  $add_profile = drush_get_option('add-profile');

  $all_packages = $manager->listPackageMachineNames();
  foreach ($packages as $name) {
    if (!in_array($name, $all_packages)) {
      return drush_set_error('', dt("The package @name does not exist.", array('@name' => $name)));
    }
  }

  if (empty($packages)) {
    $packages = $all_packages;
    $dt_args = array('!modules' => implode(', ', $packages));
    drush_print(dt('The following extensions will be exported: !modules', $dt_args));
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

/**
 * Add a component to a features module
 * the selected components.
 */
function drush_features_add() {
  if ($args = func_get_args()) {

    $manager = \Drupal::service('features.manager');
    $assigner = \Drupal::service('features_assigner');
    $generator = \Drupal::service('features_generator');
    $assigner->assignConfigPackages();

    $module = array_shift($args);
    if (empty($args)) {
      return drush_set_error('', 'No components supplied.');
    }
    $components = _drush_features_component_list();
    $options = array(
      'exported' => FALSE,
    );

    $filtered_components = _drush_features_component_filter($components, $args, $options);
    $items = $filtered_components['components'];

    if (empty($items)) {
      return drush_set_error('', 'No components to add.');
    }

    $packages = array($module);
    // If any packages exist, confirm before overwriting.
    if ($existing_packages = $manager->listPackageDirectories($packages)) {
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
    else {
      $package = $manager->initPackage($module, $module, '');
      list($full_name, $path) = $manager->getExportInfo($package);
      drush_print(dt('Will create a new extension @name in !directory', array('@name' => $full_name, '!directory' => $path)));
      if (!drush_confirm(dt('Do you really want to continue?'))) {
        drush_die('Aborting.');
      }
    }

    $config = _drush_features_build_config($items);

    $manager->assignConfigPackage($module, $config);

    // Use the write generation method.
    $method_id = \Drupal\features\Plugin\FeaturesGeneration\FeaturesGenerationWrite::METHOD_ID;

    $result = $generator->generatePackages($method_id, $packages, FALSE);
    foreach ($result as $message) {
      $type = $message['success'] ? 'success' : 'error';
      drush_log($message['message'], $message['variables'], $type);
    }
  }
  else {
    return drush_set_error('', 'No feature name given.');
  }
}


/**
 * List components, with pattern matching.
 */
function drush_features_components() {
  $args = func_get_args();

  $components = _drush_features_component_list();
  ksort($components);
  // If no args supplied, prompt with a list.
  if (empty($args)) {
    $types = array_keys($components);
    array_unshift($types, 'all');
    $choice = drush_choice($types, 'Enter a number to choose which component type to list.');
    if ($choice === FALSE) {
      return;
    }

    $args = ($choice == 0) ? array('*') : array($types[$choice]);
  }
  $options = array(
    'provided by' => TRUE,
  );
  if (drush_get_option(array('exported', 'e'), NULL)) {
    $options['not exported'] = FALSE;
  }
  elseif (drush_get_option(array('not-exported', 'o'), NULL)) {
    $options['exported'] = FALSE;
  }

  $filtered_components = _drush_features_component_filter($components, $args, $options);
  if ($filtered_components){
    _drush_features_component_print($filtered_components);
  }
}

/**
 * Returns an array of full config names given a array[$type][$component]
 * @param $items
 */
function _drush_features_build_config($items) {
  $manager = \Drupal::service('features.manager');
  $result = array();
  foreach ($items as $config_type => $item) {
    foreach ($item as $item_name => $title) {
      $result[] = $manager->getFullName($config_type, $item_name);
    }
  }
  return $result;
}


/**
 * Returns a listing of all known components, indexed by source.
 */
function _drush_features_component_list() {
  $result = array();
  $manager = \Drupal::service('features.manager');
  $config = $manager->getFullConfigCollection();
  foreach ($config as $item_name => $item) {
    $result[$item['type']][$item['name_short']] = $item['label'];
  }
  return $result;
}

/**
 * Filters components by patterns.
 */
function _drush_features_component_filter($all_components, $patterns = array(), $options = array()) {
  $options += array(
    'exported' => TRUE,
    'not exported' => TRUE,
    'provided by' => FALSE,
  );
  $pool = array();
  // Maps exported components to feature modules.
  $components_map = _drush_features_get_component_map();
  // First filter on exported state.
  foreach ($all_components as $source => $components) {
    foreach ($components as $name => $title) {
      $exported = sizeof($components_map[$source][$name]) > 0;
      if ($exported) {
        if ($options['exported']) {
          $pool[$source][$name] = $title;
        }
      }
      else {
        if ($options['not exported']) {
          $pool[$source][$name] = $title;
        }
      }
    }
  }

  $state_string = '';

  if (!$options['exported']) {
    $state_string = 'unexported';
  }
  elseif (!$options['not exported']) {
    $state_string = 'exported';
  }

  $selected = array();
  foreach ($patterns as $pattern) {
    // Rewrite * to %. Let users use both as wildcard.
    $pattern = strtr($pattern, array('*' => '%'));
    $sources = array();
    list($source_pattern, $component_pattern) = explode(':', $pattern, 2);
    // If source is empty, use a pattern.
    if ($source_pattern == '') {
      $source_pattern = '%';
    }
    if ($component_pattern == '') {
      $component_pattern = '%';
    }

    $preg_source_pattern = strtr(preg_quote($source_pattern, '/'), array('%' => '.*'));
    $preg_component_pattern = strtr(preg_quote($component_pattern, '/'), array('%' => '.*'));
    /*
     * If it isn't a pattern, but a simple string, we don't anchor the
     * pattern, this allows for abbreviating. Else, we do, as this seems more
     * natural for patterns.
     */
    if (strpos($source_pattern, '%') !== FALSE) {
      $preg_source_pattern = '^' . $preg_source_pattern . '$';
    }
    if (strpos($component_pattern, '%') !== FALSE) {
      $preg_component_pattern = '^' . $preg_component_pattern . '$';
    }
    $matches = array();

    // Find the sources.
    $all_sources = array_keys($pool);
    $matches = preg_grep('/' . $preg_source_pattern . '/', $all_sources);
    if (sizeof($matches) > 0) {
      // If we have multiple matches and the source string wasn't a
      // pattern, check if one of the matches is equal to the pattern, and
      // use that, or error out.
      if (sizeof($matches) > 1 and $preg_source_pattern[0] != '^') {
        if (in_array($source_pattern, $matches)) {
          $matches = array($source_pattern);
        }
        else {
          return drush_set_error('', dt('Ambiguous source "!source", matches !matches', array('!source' => $source_pattern, '!matches' => join(', ', $matches))));
        }
      }
      // Loose the indexes preg_grep preserved.
      $sources = array_values($matches);
    }
    else {
      return drush_set_error('', dt('No !state sources match "!source"', array('!state' => $state_string, '!source' => $source_pattern)));
    }

    // Now find the components.
    foreach ($sources as $source) {
      // Find the components.
      $all_components = array_keys($pool[$source]);
      // See if there's any matches.
      $matches = preg_grep('/' . $preg_component_pattern . '/', $all_components);
      if (sizeof($matches) > 0) {
        // If we have multiple matches and the components string wasn't a
        // pattern, check if one of the matches is equal to the pattern, and
        // use that, or error out.
        if (sizeof($matches) > 1 and $preg_component_pattern[0] != '^') {
          if (in_array($component_pattern, $matches)) {
            $matches = array($component_pattern);
          }
          else {
            return drush_set_error('', dt('Ambiguous component "!component", matches !matches', array('!component' => $component_pattern, '!matches' => join(', ', $matches))));
          }
        }
        if (!is_array($selected[$source])) {
          $selected[$source] = array();
        }
        $selected[$source] += array_intersect_key($pool[$source], array_flip($matches));
      }
      else {
        // No matches. If the source was a pattern, just carry on, else
        // error out. Allows for patterns like :*field*
        if ($preg_source_pattern[0] != '^') {
          return drush_set_error('', dt('No !state !source components match "!component"', array('!state' => $state_string, '!component' => $component_pattern, '!source' => $source)));
        }
      }
    }
  }

  // Lastly, provide feature module information on the selected components, if
  // requested.
  $provided_by = array();
  if ($options['provided by'] && $options['exported'] ) {
    foreach ($selected as $source => $components) {
      foreach ($components as $name => $title) {
        $exported = sizeof($components_map[$source][$name]) > 0;
        if ($exported) {
          $provided_by[$source . ':' . $name] = join(', ', $components_map[$source][$name]);
        }
      }
    }
  }

  return array(
    'components' => $selected,
    'sources' => $provided_by,
  );
}

/**
 * Provide a component to feature map (port of features_get_component_map).
 */
function _drush_features_get_component_map() {
  $result = array();
  $manager = \Drupal::service('features.manager');
  // recalc full config list without running assignments
  $config = $manager->getFullConfigCollection();
  $packages = $manager->getPackages();

  foreach ($config as $item_name => $item) {
    if (!isset($result[$item['type']][$item['name_short']])) {
      $result[$item['type']][$item['name_short']] = array();
    }
    if (!empty($item['package'])) {
      $package = $packages[$item['package']];
      $result[$item['type']][$item['name_short']][] = $package['machine_name'];
    }
  }

  return $result;
}

/**
 * Prints a list of filtered components.
 */
function _drush_features_component_print($filtered_components) {
  $rows = array(array(dt('Available sources')));
  foreach ($filtered_components['components'] as $source => $components) {
    foreach ($components as $name => $value) {
      $row = array($source .':'. $name);
      if (isset($filtered_components['sources'][$source .':'. $name])) {
        $row[] = dt('Provided by') . ': ' . $filtered_components['sources'][$source .':'. $name];
      }
      $rows[] = $row;
    }
  }

  drush_print_table($rows, TRUE);
}

