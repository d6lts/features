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
        '#attributes' => array('class' => 'feature-name'),
      );
      $form['info']['filename'] = array(
        '#type' => 'textfield',
        '#title' => t('Machine-readable name'),
        '#description' => t('Provide a machine-readable name for your feature. This may only contain lowercase letters, numbers and underscores. <strong>It should also avoid conflicting with the names of any existing Drupal modules.</strong>'),
        '#required' => TRUE,
        '#default_value' => '',
        '#attributes' => array('class' => 'feature-filename'),
      );
      $form['info']['description'] = array(
        '#title' => t('Description'),
        '#description' => t('Provide a description for your feature.'),
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => '',
      );
      drupal_add_js(drupal_get_path('module', 'features') .'/features.js');
      break;

    // Choose a context ===============================================
    case 1:
      $form['#theme'] = 'features_export_context_form';
      $form['#contexts'] = $contexts = context_enabled_contexts();
      $form['contexts'] = array('#tree' => TRUE);
      foreach ($contexts as $context) {
        $form['contexts']["$context->namespace-$context->attribute-$context->value"] = array(
          '#title' => $context->value,
          '#type' => 'checkbox',
        );
      }
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

        $files = array(
          "{$info['filename']}/{$info['filename']}.info" => $code[0],
          "{$info['filename']}/{$info['filename']}.module" => $code[1],
        );
        $form['download'] = array(
          '#tree' => TRUE,
          '#theme' => 'features_form_buttons',
          '#weight' => -10,
        );
        $form['download']['filename'] = array(
          '#type' => 'value',
          '#value' => function_exists('gzencode') ? "{$info['filename']}.tar.gz" : "{$info['filename']}.tar",
        );
        $form['download']['tar'] = array(
          '#type' => 'value',
          '#value' => features_tar_create($files),
        );
        $form['download']['download'] = array(
          '#type' => 'submit',
          '#value' => t('Download feature'),
          '#submit' => array('features_export_download_submit'),
        );

        $form['info'] = array(
          '#title' => "{$info['filename']}.info",
          '#type' => 'textarea',
          '#rows' => 15,
          '#default_value' => $code[0],
          '#resizable' => FALSE,
        );
        $form['module'] = array(
          '#title' => "{$info['filename']}.module",
          '#type' => 'textarea',
          '#rows' => 15,
          '#default_value' => $code[1],
          '#resizable' => FALSE,
        );
      }
      break;
  }

  // Add Next/Prev step buttons
  $form['buttons'] = array('#tree' => FALSE, '#theme' => 'features_form_buttons');
  if ($step > 0 && $step != count($steps) - 1) {
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
        foreach ($form_state['values']['contexts'] as $identifier => $value) {
          if ($value) {
            $feature['context'][] = $identifier;
          }
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
 * Download submit handler.
 */
function features_export_download_submit(&$form, &$form_state) {
  if (!empty($form_state['values']['download'])) {
    $file = $form_state['values']['download']['tar'];
    $filename = $form_state['values']['download']['filename'];
    if (function_exists('gzencode')) {
      drupal_set_header('Content-type: application/x-gzip');
    }
    else {
      drupal_set_header('Content-type: application/x-tar');
    }
    drupal_set_header('Content-Disposition: attachment; filename="'. $filename .'"');
    print $file;
    exit;
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

    // Merge items from both
    $merged = array_merge($export['items'], $module->info['feature']);

    foreach ($merged as $i => $data) {
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
 * Display a table of contexts (step 1).
 */
function theme_features_export_context_form($form) {
  // Add css
  drupal_add_css(drupal_get_path("module", "context_ui") ."/context_ui.css");

  $rows = $headings = array();
  foreach (element_children($form['contexts']) as $key) {
    $context = $form['#contexts'][$key];
    $row = array();

    $namespace = $context->namespace;
    $attribute = $context->attribute;
    $value = $context->value;
    if (isset($context->cid) && $context->cid) {
      $identifier = $context->cid;
    }
    else {
      $identifier = $key;
    }

    // If no heading has been printed for this n/a pair, do so
    if (!isset($rows["$namespace-$attribute"])) {
      $row = array('', array('data' => "<span class='context-namespace'>$namespace &gt; $attribute</span>", 'colspan' => 2));
      $rows["$namespace-$attribute"] = $row;
    }
    unset($form['contexts'][$key]['#title']);

    $rows[$key] = array(
      'data' => array(
        array(
          'data' => drupal_render($form['contexts'][$key]),
          'class' => 'context-ui-checkbox',
        ),
        array(
          'data' => '<strong>'. $value .'</strong>',
          'class' => 'context-name',
        ),
      ),
      'class' => 'context-table-row ' . $class,
    );
  }
  $output = drupal_render($form['step']);
  $output .= theme('table', array(theme('table_select_header_cell'), t('Context')), $rows, array('class' => 'context-ui-bulk-export context-ui-overview'));
  $output .= drupal_render($form);

  return $output;
}

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

/**
 * Tar creation function. Written by dmitrig01.
 *
 * @param $files
 *   A keyed array where the key is the filepath and the value is the
 *   string contents of the file.
 *
 * @return
 *   A string of the tar file contents.
 */
function features_tar_create($files) {
  $tar = '';
  foreach ($files as $name => $contents) {
    $binary_data_first = pack("a100a8a8a8a12A12",
      $name,
      '100644 ', // File permissions
      '   765 ', // UID,
      '   765 ', // GID,
      sprintf("%11s ", decoct(strlen($contents))), // Filesize,
      sprintf("%11s", decoct(time())) // Creation time
    );
    $binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12", '', '', '', '', '', '', '', '', '', '');

    $checksum = 0;
    for ($i = 0; $i < 148; $i++) {
      $checksum += ord(substr($binary_data_first, $i, 1));
    }
    for ($i = 148; $i < 156; $i++) {
      $checksum += ord(' ');
    }
    for ($i = 156, $j = 0; $i < 512; $i++, $j++) {
      $checksum += ord(substr($binary_data_last, $j, 1));
    }

    $tar .= $binary_data_first;
    $tar .= pack("a8", sprintf("%6s ", decoct($checksum)));
    $tar .= $binary_data_last;

    $buffer = str_split($contents, 512);
    foreach ($buffer as $item) {
      $tar .= pack("a512", $item);
    }
  }
  if (function_exists('gzencode')) {
    $tar = gzencode($tar);
  }
  return $tar;
}
