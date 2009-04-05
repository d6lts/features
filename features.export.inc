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

  // retrieve storage variables
  $module_name = !empty($form_state['storage']['module_name']) ? $form_state['storage']['module_name'] : '';
  $export = !empty($form_state['storage']['export']) ? $form_state['storage']['export'] : array();

  // we are re-exporting a module -- load up data and export
  if (!empty($module)) {
    $step = $form_state['storage']['step'] = 3;
    $steps[3] = t('Update your feature: !module_name', array('!module_name' => $module->name));
    $module_name = $module->name;
    $export = array_merge($module->info, features_populate($module->info['features'], $module->name));
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
      $form['name'] = array(
        '#title' => t('Name'),
        '#description' => t('Provide a name for your feature.'),
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => '',
        '#attributes' => array('class' => 'feature-name'),
      );
      $form['module_name'] = array(
        '#type' => 'textfield',
        '#title' => t('Machine-readable name'),
        '#description' => t('Provide a machine-readable name for your feature. This may only contain lowercase letters, numbers and underscores. <strong>It should also avoid conflicting with the names of any existing Drupal modules.</strong>'),
        '#required' => TRUE,
        '#default_value' => '',
        '#attributes' => array('class' => 'feature-module-name'),
      );
      $form['description'] = array(
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
      $form['sources'] = array('#tree' => TRUE);
      // By default we provide the ability to use contexts as a base.
      $contexts = context_enabled_contexts();
      $form['sources']['context'] = array(
        '#theme' => 'features_export_context_form',
        '#context' => $contexts,
      );
      foreach ($contexts as $context) {
        $form['sources']['context']["$context->namespace-$context->attribute-$context->value"] = array(
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

      foreach ($export['conflicts'] as $type => $messages) {
        foreach ($messages as $msg) {
          drupal_set_message($msg, $type);
        }
      }

      // Display each set of components and options for adding to the components
      foreach ($export['features'] as $module_name => $items) {
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
      $form['detected']['dependencies'] = array(
        '#type' => 'markup',
        '#title' => t('Module dependencies'),
        '#value' => theme('item_list', array_keys($export['dependencies'])),
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
      if ($files = features_export_render($export, $module_name, TRUE)) {
        $filename = function_exists('gzencode') ? "{$module_name}.tar.gz" : "{$module_name}.tar";
        $form['module_display'] = array(
          '#type' => 'markup',
          '#value' => "<h3 class='features-download'>{$filename}</h3>",
        );

        $form['download'] = array('#tree' => TRUE, '#theme' => 'features_form_buttons');
        $form['download']['module_name'] = array('#type' => 'value', '#value' => $module_name);
        $form['download']['files'] = array('#type' => 'value', '#value' => $files);

        $form['download']['download'] = array(
          '#type' => 'submit',
          '#value' => t('Download feature'),
          '#submit' => array('features_export_download_submit'),
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
      $export = array(
        'name' => $form_state['values']['name'],
        'description' => $form_state['values']['description'],
      );
      $form_state['storage']['export'] = $export;
      $form_state['storage']['module_name'] = $form_state['values']['module_name'];
      break;

    // Step 1: Convert sources into export object
    case 1:
      $module_name = $form_state['storage']['module_name'];
      $export = $form_state['storage']['export'];

      // Retrieve export
      $feature = array();
      foreach (element_children($form['sources']) as $elem) {
        if (!empty($form_state['values']['sources'][$elem])) {
          foreach ($form_state['values']['sources'][$elem] as $identifier => $value) {
            if ($value) {
              $feature[$elem][] = $identifier;
            }
          }
        }
      }

      $export = array_merge($export, features_populate($feature, $module_name));
      $form_state['storage']['export'] = $export;
      break;

    // Step 2: Update export object based on user input
    case 2:
      $module_name = $form_state['storage']['module_name'];
      $export = $form_state['storage']['export'];

      // Update export array based on what's been selected
      foreach ($export['features'] as $module_name => $items) {
        if (!empty($form_state['values']['added'][$module_name])) {
          foreach ($form_state['values']['added'][$module_name] as $item => $value) {
            if ($value) {
              $export['features'][$module_name][$item] = $item;
            }
            else if (!empty($export['features'][$module_name][$item])) {
              unset($export['features'][$module_name][$item]);
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
      $export = array_merge($export, features_populate($export['features'], $module_name));
      $export['dependencies'] = _features_export_minimize_dependencies($export['dependencies']);
      $form_state['storage']['export'] = $export;
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
  if (!empty($form_state['values']['download']['files'])) {
    $files = $form_state['values']['download']['files'];
    $module_name = $form_state['values']['download']['module_name'];
    features_export_download_files($files, $module_name);
  }
}

/**
 * Delivers files to the user through a tarball download.
 */
function features_export_download_files($files, $module_name) {
  $tar = array();
  foreach ($files as $path => $file_contents) {
    $tar["{$module_name}/{$module_name}.$path"] = $file_contents;
  }
  $tar = features_tar_create($tar);
  $header = function_exists('gzencode') ? 'Content-type: application/x-gzip' : 'Content-type: application/x-tar';
  $filename = function_exists('gzencode') ? "{$module_name}.tar.gz" : "{$module_name}.tar";
  drupal_set_header($header);
  drupal_set_header('Content-Disposition: attachment; filename="'. $filename .'"');
  print $tar;
  exit;
}

/**
 * @param $items
 * @param $module_name
 * @return
 */
function features_populate($items, $module_name) {
  $stub = array('features' => array(), 'dependencies' => array(), 'conflicts' => array());
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
 * Render feature export into an array representing its files.
 *
 * @param $export
 *  An exported feature definition.
 * @param $module_name
 *  The name of the module to be exported.
 * @param $reset
 *  Boolean flag for resetting the module cache. Only set to true when
 *  doing a final export for delivery.
 *
 * @return array of info file and module file contents.
 */
function features_export_render($export, $module_name, $reset = FALSE) {
  global $base_url;

  $code = array();
  $existing = features_get_modules($module_name, $reset);

  // Prepare info string -- if module exists, merge into its existing info file
  if ($existing) {
    $info = $existing->info;
  }
  else {
    $info = array(
      'core' => '6.x',
      'package' => 'Features',
      'feature_uri' => $base_url,
      'feature_timestamp' => time(),
    );
  }
  $export = array_merge($info, $export);
  // A couple of special cases.
  if (!empty($export['dependencies'])) {
    $export['dependencies'] = array_values($export['dependencies']);
  }
  if (!empty($export['conflicts'])) {
    unset($export['conflicts']);
  }
  $code['info'] = features_export_info($export);

  // Prepare the defaults & features files
  $code['defaults.inc'] = $code['features.inc'] = array();

  ksort($export['features']);
  foreach ($export['features'] as $module => $data) {
    if (!empty($data)) {
      // Attempt to load inc file for the module, will fail silently if the file
      // doesn't exist.
      module_load_include('inc', 'features', "includes/features.$module");

      if (module_hook($module, 'features_export_render')) {
        $hooks = module_invoke($module, 'features_export_render', $module_name, $data);
        foreach ($hooks as $hook_name => $hook_code) {
          $code['features.inc'][$hook_name] = features_export_render_features($module_name, $hook_name, $hook_code);
          $code['defaults.inc'][$hook_name] = features_export_render_defaults($module_name, $hook_name, $hook_code);
        }
      }
    }
  }
  $code['features.inc'] = implode("\n\n", $code['features.inc']);
  $code['features.inc'] = "<?php\n\n{$code['features.inc']}\n";

  $code['defaults.inc'] = implode("\n\n", $code['defaults.inc']);
  $code['defaults.inc'] = "<?php\n\n{$code['defaults.inc']}\n";

  // Prepare the module
  // If module exists, let it be and include it in the files
  if ($existing) {
    $code['module'] = file_get_contents($existing->filename);
  }
  // Add a stub module to include the defaults
  else {
    $code['module'] = "<?php\n\ninclude_once('{$module_name}.features.inc');\n";
  }
  return $code;
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
    $export = features_populate($module->info['features'], '');

    // Render and run an export of the current state.
    $export = array_merge($export, $module->info);
    $eval_namespace = "_features_comparison_{$module->name}";
    $code = features_export_render($export, $eval_namespace);
    $php = $code['defaults.inc'];
    $php = substr_replace($php, '', strpos($php, "<?php"), 5);

    ob_start();
    eval($php);
    ob_end_clean();

    $overridden = array();

    // Retrieve default hooks & compare against eval'd versions
    $export_functions = features_get_default_hooks();

    // Merge items from both
    $merged = array_merge($export['features'], $module->info['features']);

    foreach ($merged as $i => $data) {
      if (isset($export_functions[$i])) {
        // Call the eval'd function and collect results
        // Use the underscore version of the function name as we don't want
        // to go through module_load_include().
        $fname = "_{$eval_namespace}_{$export_functions[$i]}";
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
            // Special cases for objects -- some (views) provide their own
            // export methods which we need to respect.
            if (is_object($current[$i][$j])) {
              switch (get_class($current[$i][$j])) {
                case 'view':
                  $a = $current[$i][$j]->export();
                  $b = $default[$i][$j]->export();
                  break;
                default:
                  $a = features_var_export($current[$i][$j]);
                  $b = features_var_export($default[$i][$j]);
                  break;
              }
              $a = _features_linetrim(explode("\n", $a));
              $b = _features_linetrim(explode("\n", $b));

              $different = $a !== $b;
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
  $hooks = array(
    'views' => 'views_default_views',
    'imagecache' => 'imagecache_default_presets',
    'node' => 'node_info',
    'context' => 'context_default_contexts',
    // The following are hooks provided by features.module as a temporary stopgap
    // modules without exportables.
    'content' => 'content_default_fields',
    'menu' => 'menu_default_items',
  );

  // A module can implement 'hook_features_defaults' to let us know about the
  // default hook for what it provides. Currently this limits us to a single
  // item per module. File this under 'todo'.
  foreach (module_implements('features_defaults') as $module) {
    $hooks[$module] = module_invoke($module, 'features_defaults');
  }
  return $hooks;
}

/**
 * Return a code string representing an implementation of a module hook.
 * Includes the module's defaults .inc and calls the private helper function.
 */
function features_export_render_features($module, $hook, $code) {
  $output = array();
  $output[] = "/**";
  $output[] = " * Implementation of hook_{$hook}().";
  $output[] = " */";
  $output[] = "function {$module}_{$hook}() {";
  $output[] = "  module_load_include('inc', '{$module}', '{$module}.defaults');";
  $output[] = "  return _{$module}_{$hook}();";
  $output[] = "}";
  return implode("\n", $output);
}

/**
 * Return a code string representing an implementation of a defaults module hook.
 */
function features_export_render_defaults($module, $hook, $code) {
  $output = array();
  $output[] = "/**";
  $output[] = " * Helper to implemetation of hook_{$hook}().";
  $output[] = " */";
  $output[] = "function _{$module}_{$hook}() {";
  $output[] = $code;
  $output[] = "}";
  return implode("\n", $output);
}

/**
 * Theme functions ====================================================
 */

/**
 * Display a table of contexts.
 */
function theme_features_export_context_form($form) {
  // Add css
  drupal_add_css(drupal_get_path("module", "context_ui") ."/context_ui.css");

  $rows = $headings = array();
  foreach (element_children($form) as $key) {
    $context = $form['#context'][$key];
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
    unset($form[$key]['#title']);

    $rows[$key] = array(
      'data' => array(
        array(
          'data' => drupal_render($form[$key]),
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
 * Generate code friendly to the Drupal .info format from a structured array.
 *
 * @param $info
 *   An array of parameters to put in a module's .info file.
 *
 * @return
 *   A code string ready to be written to a module's .info file.
 */
function features_export_info($info) {
  $code = array();
  foreach ($info as $k => $v) {
    if (is_array($v)) {
      $first = array_shift(array_keys($v));
      if (is_numeric($first)) {
        sort($v);
      }
      else {
        ksort($v);
      }

      foreach ($v as $l => $m) {
        if (is_numeric($l)) {
          $key = '';
        }
        else {
          $key = $l;
        }
        if (is_array($m)) {
          sort($m);
          foreach ($m as $n => $o) {
            $code[] = "{$k}[$key][] = \"{$o}\"";
          }
        }
        else {
          $code[] = "{$k}[$key] = \"{$m}\"";
        }
      }
    }
    else {
      $code[] = "{$k} = \"{$v}\"";
    }
  }
  $code = implode("\n", $code) ."\n";
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
