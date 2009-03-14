<?php
// $Id$

/**
 * Export form.
 */
function features_export_form($form_state, $module = NULL) {
  $steps = array(
    t('Step 1: provide basic information'),
    t('Step 2: choose sources'),
    t('Step 3: confirm components'),
    t('Step 4: download your feature'),
  );

  // we are re-exporting a module -- load up data and export
  if (!empty($module)) {
    $step =
    $form_state['storage']['step'] = 3;

    $steps[3] = t('Update your feature: !module_name', array('!module_name' => $module->name));

    $form_state['storage']['info'] = array(
      'name' => $module->info['name'],
      'filename' => $module->name,
      'description' => $module->info['description'],
    );
    $form_state['storage']['export_final'] = features_populate($module->info['feature'], $module->name);
  }
  // we are coming in without a step, so default to step 1
  else {
    $step =
    $form_state['storage']['step'] = empty($form_state['storage']['step']) ? 0 : $form_state['storage']['step'];
  }

  $form = array();
  $form['step'] = array(
    '#type' => 'item',
    '#value' => "<strong>{$steps[$step]}</strong>",
  );

  switch ($step) {

    // Provide additional information =================================
    case 0:
      $form['info'] = array(
        '#tree' => TRUE,
      );
      $form['info']['name'] = array(
        '#title' => t('Name'),
        '#description' => t('Provide a name for your feature.'),
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => '',
      );
      $form['info']['filename'] = array(
        '#title' => t('Name (machine-readable)'),
        '#description' => t('Provide a machine-readable name for your feature. This may only contain lowercase letters, numbers and underscores. <strong>It should also avoid conflicting with the names of any existing Drupal modules.</strong>'),
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => '',
      );
      $form['info']['description'] = array(
        '#title' => t('Description'),
        '#description' => t('Provide a description for your feature.'),
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => '',
      );
      break;

    // Choose a context ===============================================
    case 1:
      // Collect enabled contexts into tree'd array.
      $options = array();
      $contexts = context_enabled_contexts();
      foreach ($contexts as $identifier => $context) {
        if (!isset($options["{$context->namespace}-{$context->attribute}"])) {
          $options["{$context->namespace}-{$context->attribute}"] = array();
        }
        $options["{$context->namespace}-{$context->attribute}"][$identifier] = $context->value;
      }

      $form['contexts'] = array(
        '#title' => t('Contexts'),
        '#description' => t('Choose contexts to build your feature from'),
        '#type' => 'select',
        '#multiple' => TRUE,
        '#size' => 6,
        '#options' => $options,
      );
      break;

    // Confirm components =============================================
    case 2:
      $form['#theme'] = 'features_export_form_confirm';
      $form['detected'] =
      $form['added'] = array('#tree' => TRUE);

      $export = $form_state['storage']['export'];
      foreach ($export['conflicts'] as $type => $messages) {
        foreach ($messages as $msg) {
          drupal_set_message($msg, $type);
        }
      }

      // Display each set of components and options for adding to the components
      foreach ($export['items'] as $module_name => $items) {
        $module = features_get_modules($module_name);

        $form['detected'][$module_name] = array(
          '#type' => 'markup',
          '#title' => $module->info['name'],
          '#value' => theme('item_list', array_keys($items)),
        );

        $options = features_export_options($module_name);
        if (!empty($options)) {
          $options = array_diff_key($options, $items);
          $form['added'][$module_name] = array(
            '#type' => 'checkboxes',
            '#options' => $options,
          );
        }
        else {
          $form['added'][$module_name] = array(
            '#type' => 'markup',
            '#value' => "<em>". t('This module does not support any options.') ."</em>",
          );
        }
      }

      // Dependencies
      $dependencies = $export['dependencies'];
      $form['detected']['dependencies'] = array(
        '#type' => 'markup',
        '#title' => t('Module dependencies'),
        '#value' => theme('item_list', array_keys($dependencies)),
      );

      $options = array();
      foreach (features_get_modules() as $module_name => $info) {
        if ($info->status && !empty($info->info)) {
          $options[$module_name] = $info->info['name'];
        }
      }
      $options = array_diff_key($options, $export['dependencies']);

      $form['added']['dependencies'] = array(
        '#tree' => TRUE,
        '#type' => 'checkboxes',
        '#options' => $options,
      );
      break;

    // Download/export ================================================
    case 3:
      $info = $form_state['storage']['info'];
      $export = $form_state['storage']['export_final'];
      $form['#theme'] = 'features_export_form_final';

      if ($code = features_export_render($export, $info)) {
        $form['info'] = array(
          '#description' => t('Copy and paste the following code into !module/!filename in your modules directory.', array('!module' => $info['filename'], '!filename' => "{$info['filename']}.info")),
          '#title' => "{$info['filename']}.info",
          '#type' => 'textarea',
          '#rows' => 20,
          '#default_value' => $code[0],
          '#resizable' => FALSE,
        );
        $form['module'] = array(
          '#description' => t('Copy and paste the following code into !module/!filename in your modules directory.', array('!module' => $info['filename'], '!filename' => "{$info['filename']}.module")),
          '#title' => "{$info['filename']}.module",
          '#type' => 'textarea',
          '#rows' => 20,
          '#default_value' => $code[1],
          '#resizable' => FALSE,
        );
      }
      break;
  }

  // Add Next/Prev step buttons
  $form['buttons'] = array('#tree' => FALSE, '#theme' => 'features_form_buttons');
  if ($step > 0) {
    $form['buttons']['prev'] = array('#value' => t('Previous'), '#type' => 'submit');
  }
  if ($step < count($steps) - 1) {
    $form['buttons']['next'] = array('#value' => t('Next'), '#type' => 'submit');
    if (drupal_get_messages('error', FALSE)) {
      $form['buttons']['message'] = array(
        '#type' => 'markup',
        '#value' => "<div class='description'>". t('You should resolve all errors with your feature before continuing.') ."</div>",
      );
    }
  }

  return $form;
}

/**
 * Export form submit handler.
 */
function features_export_form_submit($form, &$form_state) {
  // tell Drupal we are redrawing the same form
  $form_state['rebuild'] = TRUE;

  switch ($form_state['storage']['step']) {

    // Step 0: Store info
    case 0:
      $form_state['storage']['info'] = $form_state['values']['info'];
      break;

    // Step 1: Convert sources into export object
    case 1:
      // Module name
      $module_name = $form_state['storage']['info']['filename'];

      // Retrieve export
      $feature = array();
      if (!empty($form_state['values']['contexts'])) {
        foreach ($form_state['values']['contexts'] as $identifier) {
          $feature['context'][] = $identifier;
        }
      }
      $export = features_populate($feature, $module_name);
      $form_state['storage']['export'] = $export;
      break;

    // Step 2: Update export object based on user input
    case 2:
      $export = $form_state['storage']['export'];

      // Update export array based on what's been selected
      foreach ($export['items'] as $module_name => $items) {
        if (!empty($form_state['values']['added'][$module_name])) {
          foreach ($form_state['values']['added'][$module_name] as $item => $value) {
            if ($value) {
              $export['items'][$module_name][$item] = $item;
            }
            else if (!empty($export['items'][$module_name][$item])) {
              unset($export['items'][$module_name][$item]);
            }
          }
        }
      }
      // Update dependencies
      if (!empty($form_state['values']['added']['dependencies'])) {
        foreach ($form_state['values']['added']['dependencies'] as $item => $value) {
          if ($value) {
            $export['dependencies'][$item] = $item;
          }
          else if (!empty($export['dependencies'][$item])) {
            unset($export['dependencies'][$item]);
          }
        }
      }

      // Build final export array
      $module_name = $form_state['storage']['info']['filename'];

      $final = $export;
      $final = array_merge($final, features_populate($export['items'], $module_name));
      $final['dependencies'] = _features_export_minimize_dependencies($final['dependencies']);

      $form_state['storage']['export_final'] = $final;
      break;
  }

  // check the button that was clicked and action the step chagne
  if ($form_state['clicked_button']['#id'] == 'edit-prev') {
    $form_state['storage']['step']--;
  }
  elseif ($form_state['clicked_button']['#id'] == 'edit-next') {
    $form_state['storage']['step']++;
  }
}

/**
 * @param $items
 * @param $module_name
 * @return
 */
function features_populate($items, $module_name) {
  $stub = array('items' => array(), 'dependencies' => array(), 'conflicts' => array());
  $export = _features_populate($items, $stub, $module_name);
  $export['dependencies'] = _features_export_minimize_dependencies($export['dependencies'], $module_name);
  return $export;
}

/**
 * Iterate and descend into a feature definition to extract module
 * dependencies and feature definition. Calls hook_features_export for modules
 * that implement it.
 *
 * @param $pipe
 *  Associative of array of module => info-for-module
 * @param $export
 *  Associative array of items, and module dependencies which define a feature.
 *  Passed by reference.
 *
 * @return fully populated $export array.
 */
function _features_populate($pipe, &$export, $module_name = '') {
  foreach ($pipe as $module => $data) {
    // Attempt to load inc file for the module, will fail silently if the file
    // doesn't exist.
    module_load_include('inc', 'features', "includes/features.$module");

    if (module_hook($module, 'features_export')) {
      $function = "{$module}_features_export";
      // Pass module-specific data and export array (should be done by reference)
      $more = $function($data, $export, $module_name);
      // Allow for export functions to request additional exports.
      if (!empty($more)) {
        _features_populate($more, $export, $module_name);
      }
    }
  }

  return $export;
}

/**
 * Iterates over a list of dependencies and kills modules that are
 * captured by other modules 'higher up'.
 */
function _features_export_minimize_dependencies($dependencies, $module_name = '') {
  // Ensure that the module doesn't depend upon itself
  if (!empty($module_name) && !empty($dependencies[$module_name])) {
    unset($dependencies[$module_name]);
  }
  foreach ($dependencies as $k => $v) {
    if (empty($v)) {
      unset($dependencies[$k]);
    }
    else {
      $module = features_get_modules($v);
      if ($module && !empty($module->info['dependencies'])) {
        foreach ($module->info['dependencies'] as $dependency) {
          if (!empty($dependencies[$dependency])) {
            unset($dependencies[$dependency]);
          }
        }
      }
    }
  }
  return $dependencies;
}

/**
 * Render feature export into a .info and .module file.
 *
 * @param $export
 *  An exported feature definition.
 * @param $module_info
 *  Meta information about the module.
 *
 * @return array of info file and module file contents.
 */
function features_export_render($export, $module_info = array()) {
  global $base_url;

  // Standard dot-info file metadata first.
  $info = array(
    'name' => $module_info['name'],
    'description' => $module_info['description'],
    'core' => '6.x',
    'package' => 'Features',
    '#comment_dependencies' => '; Dependencies',
    'dependencies' => array_keys($export['dependencies']),
    '#comment_features' => '; Feature components',
    'feature' => array(),
    '#comment_version' => '; Feature information',
    'feature_uri' => $base_url,
    'feature_timestamp' => time(),
  );
  foreach ($export['items'] as $key => $item) {
    $info['feature'][$key] = $item;
  }
  $info = features_export_info($info);

  // Now the dot-module.
  $code = array();
  foreach ($export['items'] as $module => $data) {
    if (!empty($data)) {
      // Attempt to load inc file for the module, will fail silently if the file
      // doesn't exist.
      module_load_include('inc', 'features', "includes/features.$module");

      if (module_hook($module, 'features_export_render')) {
        $function = "{$module}_features_export_render";
        $code[$module] = $function($module_info['filename'], $data);
      }
    }
  }
  $code = implode("\n\n", $code);
  $code = "<?php\n\n{$code}\n";

  return array($info, $code);
}

/**
 * Retrieve export options suitable for a FormAPI options array for a given module.
 */
function features_export_options($module) {
  $options = array();
  module_load_include('inc', 'features', "includes/features.$module");
  if (module_hook($module, 'features_export_options')) {
    $options = module_invoke($module, 'features_export_options');
  }
  return $options;
}

/**
 * Detect the difference of a feature.
 */
function features_detect_overrides($module) {
  static $cache;
  if (!isset($cache)) {
    $cache = array();
  }
  if (!isset($cache[$module->name])) {
    // Make necessary inclusions
    if (module_exists('views')) {
      views_include('view');
    }

    // Rebuild feature from .info file description.
    $export = features_populate($module->info['feature'], '');

    // Render and run an export of the current state.
    $module->info['filename'] = '_features_comparison_' . $module->name;
    list($i, $m) = features_export_render($export, $module->info);
    $m = substr_replace($m, '', strpos($m, "<?php"), 5);
    ob_start();
    eval($m);
    ob_end_clean();

    $overridden = array();

    // Retrieve default hooks & compare against eval'd versions
    $export_functions = features_get_default_hooks();
    foreach ($module->info['feature'] as $i => $data) {
      if (isset($export_functions[$i])) {
        // Call the eval'd function and collect results
        $fname = $module->info['filename'] .'_'. $export_functions[$i];
        if (function_exists($fname)) {
          $current[$i] = call_user_func($fname);
        }

        // Call the existing in-code function and collect results
        $fname = $module->name .'_'. $export_functions[$i];
        if (function_exists($fname)) {
          $default[$i] = call_user_func($fname);
        }

        // Compare, and push differences into the overrides array
        if (isset($current[$i])) {
          foreach ($current[$i] as $j => $k) {
            // We serialize objects to eliminate different instantiations of the "same" object
            if (is_object($current[$i][$j])) {
              $different = serialize($current[$i][$j]) !== serialize($default[$i][$j]);
            }
            else {
              $different = $current[$i][$j] !== $default[$i][$j];
            }
            if ($different) {
              $overridden[$i] = array(
                'default' => $default[$i][$j],
                'current' => $current[$i][$j],
              );
            }
          }
        }
      }
    }
    $cache[$module->name] = $overridden;
  }
  return $cache[$module->name];
}

/**
 * Return an array of default functions
 */
function features_get_default_hooks() {
  // Fake a registry of default functions for now.
  return array(
    'views' => 'views_default_views',
    'imagecache' => 'imagecache_default_presets',
    'node' => 'node_info',
    'context' => 'context_default_contexts',
    // The following are hooks provided by features.module as a temporary stopgap
    // modules without exportables.
    'content' => 'content_default_fields',
    'menu' => 'menu_default_items',
  );
}

/**
 * Theme functions ====================================================
 */

/**
 * Theme function for features_export_form (step 2)
 */
function theme_features_export_form_confirm($form) {
  drupal_add_css(drupal_get_path('module', 'features') .'/features.css');

  $output = drupal_render($form['step']);
  $rows = array();
  foreach (element_children($form['detected']) as $element) {
    $row = array();
    $row[] = "<strong>{$form['detected'][$element]['#title']}</strong>";
    unset($form['detected'][$element]['#title']);

    $row[] = drupal_render($form['detected'][$element]);
    $row[] = drupal_render($form['added'][$element]);
    $rows[] = $row;
  }
  $output .= theme('table', array('', t('Auto-detected components'), t('Select additional components')), $rows, array('class' => 'features-export'));
  $output .= drupal_render($form);
  return $output;
}

/**
 * Theme function for features_export_form (step 3)
 */
function theme_features_export_form_final($form) {
  drupal_add_css(drupal_get_path('module', 'features') .'/features.css');

  $output = drupal_render($form['step']);

  $header = array();
  $header[] = $form['info']['#title'];
  $header[] = $form['module']['#title'];

  unset($form['info']['#title']);
  unset($form['module']['#title']);

  $rows = array();
  $row = array();
  $row[] = drupal_render($form['info']);
  $row[] = drupal_render($form['module']);
  $rows[] = $row;

  $output .= theme('table', $header, $rows, array('class' => 'features-export features-export-final'));
  $output .= drupal_render($form);
  return $output;
}

/**
 * Export var function -- from Views.
 */
function features_var_export($var, $prefix = '', $multiple = TRUE) {
  if (is_array($var)) {
    if (empty($var)) {
      $output = 'array()';
    }
    else {
      $output = "array(\n";
      foreach ($var as $key => $value) {
        $output .= "  '$key' => ". context_var_export($value, $prefix . ($multiple? '' : '  ')) .",\n";
      }
      $output .= ')';
    }
  }
  else if (is_bool($var)) {
    $output = $var ? 'TRUE' : 'FALSE';
  }
  else {
    $output = var_export($var, TRUE);
  }

  if ($prefix) {
    $output = str_replace("\n", "\n$prefix", $output);
  }

  return $output;
}

/**
 * Generate code friendly to the Drupal .info format from a structured array.
 *
 * @param $info
 *   An array of parameters to put in a module's .info file.
 *
 * @return
 *   A code string ready to be written to a module's .info file.
 */
function features_export_info($info) {
  // Render the info array into a string
  // @TODO: we should probably break this out.
  $code = array();
  foreach ($info as $k => $v) {
    if (strpos($k, '#') === 0) {
      $code[] = '';
      $code[] = $v;
    }
    else {
      if (is_array($v)) {
        foreach ($v as $l => $m) {
          if (is_array($m)) {
            foreach ($m as $n => $o) {
              $code[] = "{$k}[$l][] = \"{$o}\"";
            }
          }
          else {
            $code[] = "{$k}[] = \"{$m}\"";
          }
        }
      }
      else {
        $code[] = "{$k} = \"{$v}\"";
      }
    }
  }
  $code = implode("\n", $code);
  return $code;
}
