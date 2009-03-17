<?php
// $Id$

/**
 * admin/build/features page callback.
 */
function features_admin_form($feature = NULL) {
  // Load export functions to use in comparison.
  module_load_include('inc', 'features', 'features.export');

  // Clear the module cache
  module_rebuild_cache();

  $form = array('#theme' => 'features_form');

  $form['feature'] =
  $form['links'] =
  $form['version'] =
  $form['weight'] =
  $form['status'] = array(
    '#tree' => TRUE,
  );

  $modules = features_get_modules('', TRUE);

  // Generate features form.
  foreach (features_get_features('', TRUE) as $name => $module) {
    $unmet_dependencies = array();
    foreach ($module->info['dependencies'] as $dependency) {
      if (empty($modules[$dependency])) {
        $unmet_dependencies[] = theme('features_module_status', FEATURES_MODULE_MISSING, $dependency);
      }
    }

    $form['feature'][$name] = array(
      '#type' => 'value',
      '#value' => $module,
    );

    $form['status'][$name] = array(
      '#type' => 'checkbox',
      '#title' => $module->info['name'],
      '#description' => !empty($unmet_dependencies) ? t('Unmet dependencies: !dependencies', array('!dependencies' => implode(', ', $unmet_dependencies))) : $module->info['description'],
      '#default_value' => $module->status,
      '#disabled' => !empty($unmet_dependencies) ? TRUE : FALSE,
    );

    $uri = !empty($module->info['feature_uri']) ? $module->info['feature_uri'] : t('Unavailable');
    $timestamp = !empty($module->info['feature_timestamp']) ? format_date($module->info['feature_timestamp'], 'small') : '';
    $timestamp = !empty($timestamp) ? "<div class='description'>$timestamp</div>" : '';
    $sign = "$uri $timestamp";

    $form['sign'][$name] = array(
      '#type' => 'markup',
      '#value' => $sign,
    );

    if ($module->status) {
      $storage = count(features_detect_overrides($module)) ? FEATURES_OVERRIDDEN : FEATURES_DEFAULT;
      $actions = array();
      $actions[] = l(t('View'), "admin/build/features/{$name}");
      switch ($storage) {
        case FEATURES_OVERRIDDEN:
          $state = l(theme('features_storage', $storage), "admin/build/features/{$name}/compare", array('html' => TRUE));
          $actions[] = l(t('Re-export'), "admin/build/features/{$name}/export");
          break;
        case FEATURES_DEFAULT:
          $state = theme('features_storage', $storage);
          break;
      }
    }

    $form['state'][$name] = array(
      '#type' => 'markup',
      '#value' => !empty($state) ? $state : '',
    );
    $form['actions'][$name] = array(
      '#type' => 'markup',
      '#value' => !empty($actions) ? implode(' | ', $actions) : '',
    );
  }

  $form['buttons'] = array(
    '#theme' => 'features_form_buttons',
  );
  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save settings'),
    '#submit' => array('features_form_submit'),
  );
  return $form;
}

/**
 * Display the components of a feature.
 */
function features_admin_components($feature) {
  module_load_include('inc', 'features', 'features.export');
  $conflict = features_detect_overrides($feature);

  // Retrieve basic information
  $info = array();
  $info['name'] = $feature->info['name'];
  $info['description'] = $feature->info['description'];

  // Iterate over dependencies and retrieve status for display
  $dependencies = array();
  foreach ($feature->info['dependencies'] as $dependency) {
    $status = features_get_module_status($dependency);
    $dependencies[$dependency] = $status;
  }

  // Iterate over components and retrieve status for display
  $components = $feature->info['features'];
  $conflicts = array();
  foreach ($feature->info['features'] as $type => $items) {
    if (!empty($conflict[$type])) {
      $conflicts[$type] = l(theme('features_storage', FEATURES_OVERRIDDEN), "admin/build/features/{$feature->name}/compare", array('html' => TRUE));
      unset($conflict[$type]);
    }
    else {
      $conflicts[$type] = theme('features_storage', FEATURES_DEFAULT);
    }
  }
  // There are new additions -- show
  if (!empty($conflict)) {
    foreach ($conflict as $type => $items) {
      // If exportables are standardized with keyed arrays, this would work much better!
      $components[$type] = array();
      $conflicts[$type] = l(theme('features_storage', FEATURES_OVERRIDDEN), "admin/build/features/{$feature->name}/compare", array('html' => TRUE));
    }
  }

  return theme('features_admin_components', $info, $dependencies, $components, $conflicts);
}

/**
 * Submit handler for the 'manage features' form
 */
function features_form_submit(&$form, &$form_state) {
  $features = $form_state['values']['feature'];
  $install = array();
  $disable = array();
  if (!empty($features)) {
    foreach ($features as $name => $feature) {
      // Enable feature
      if ($form_state['values']['status'][$name] && !module_exists($name)) {
        if (!empty($feature->info['dependencies'])) {
          foreach ($feature->info['dependencies'] as $dependency) {
            if (!module_exists($dependency)) {
              $install[] = $dependency;
            }
          }
        }
        $install[] = $name;
      }
      // Disable feature
      else if (!$form_state['values']['status'][$name] && module_exists($name)) {
        $disable[] = $name;
      }
    }

    if (!empty($install)) {
      module_enable($install);

      // Make sure the install API is available.
      include_once './includes/install.inc';
      drupal_install_modules($install);
    }
    if (!empty($disable)) {
      module_disable($disable);
    }
  }

  // Clear the cache to respect any changes.
  features_get_modules(NULL, TRUE);
  $form['#redirect'] = 'admin/build/features/cache-clear';
}

/**
 * Cache clear menu callback.
 */
function features_cache_clear() {
  drupal_rebuild_theme_registry();
  node_types_rebuild();
  menu_rebuild();
  drupal_goto('admin/build/features');
}

/**
 * Page callback to display the differences between what's in code and 
 * what is in the db.
 *
 * @param $module
 *  The name of the feature module to check display differences for.
 *
 * @return Themed display of what is different.
 */
function features_feature_comparison($feature) {
  drupal_add_css(drupal_get_path('module', 'features') .'/features.css');
  module_load_include('inc', 'features', 'features.export');

  $conflict = features_detect_overrides($feature);

  // There are differences
  if (!empty($conflict)) {
    // Diff is installed
    if (module_exists('diff')) {
      module_load_include('php', 'diff', 'DiffEngine');
      $formatter = new DrupalDiffFormatter();
      foreach ($conflict as $k => $v) {
        if (module_exists('diff')) {
          if (is_object($v['default']) && get_class($v['default']) == 'view' ){
            $old = explode("\n", $v['default']->export());
            $new = explode("\n", $v['current']->export());
          }
          else {
            $old = explode("\n", features_var_export($v['default']));
            $new = explode("\n", features_var_export($v['current']));
          }
          $diff = new Diff($old, $new);
          $output .= '<h3>' . $k . '</h3>';
          $output .= theme('diff_table', array(), $formatter->format($diff), array('class' => 'diff'));
        }
        else {
          $items[] = $k;
        }
      }
    }
    // Diff is not installed
    else {
      $output = "<div class='features-empty'>". t('Please install the diff module to view changes.') ."</div>";
    }
  }
  // No changes
  else {
    $output = "<div class='features-empty'>". t('No changes have been made to this feature.') ."</div>";
  }
  $output = "<div class='features-comparison'>{$output}</div>";
  return $output;
}

/**
 * Theme for features management form.
 */
function theme_features_form($form) {
  drupal_add_css(drupal_get_path('module', 'features') .'/features.css');
  drupal_add_js(drupal_get_path('module', 'features') .'/features.js');

  $output = '';

  $header = array('', t('Feature'), t('Signature'), t('State'), t('Actions'));

  $rows = array();
  foreach (element_children($form['status']) as $element) {
    // Yank title & description fields off the form element for
    // rendering in their own cells.
    $name = "<div class='feature'>";
    $name .= "<strong>{$form['status'][$element]['#title']}</strong>";
    $name .= "<div class='description'>{$form['status'][$element]['#description']}</div>";
    $name .= "</div>";
    unset($form['status'][$element]['#title']);
    unset($form['status'][$element]['#description']);

    $row = array(
      'status' => drupal_render($form['status'][$element]),
      'name' => $name,
      'sign' => drupal_render($form['sign'][$element]),
      'state' => drupal_render($form['state'][$element]),
      'actions' => drupal_render($form['actions'][$element]),
    );

    // Determine row classes
    $class = $form['status'][$element]['#default_value'] ? 'enabled' : 'disabled';

    // Collect data + classes & add to master array.
    foreach ($row as $key => $data) {
      $row[$key] = array('data' => $data, 'class' => $key);
    }
    $rows[] = array('data' => $row, 'class' => $class);
  }
  $output .= theme('table', $header, $rows, array('id' => 'features-form-table', 'class' => 'features'));

  // Prevent section from being rendered by drupal_render().
  unset($form['features']);

  $output .= drupal_render($form['buttons']);
  $output .= drupal_render($form);
  return $output;
}
