<?php

/**
 * @file
 * @todo.
 */

/**
 * Form constructor for features export form.
 *
 * Acts as a router based on the form_state.
 *
 * @param object|null $feature
 *   The feature object, if available. NULL by default.
 *
 * @see features_export_build_form_submit()
 * @ingroup forms
 */
function features_export_form($form, $form_state, $feature = NULL) {
  module_load_include('inc', 'features', 'features.export');
  features_include();

  $form = array(
    '#attributes' => array('class' => array('features-export-form')),
    '#feature' => isset($feature) ? $feature : NULL,
  );
  $form['info'] = array(
    '#type' => 'fieldset',
    '#title' => t('General Information'),
    '#tree' => FALSE,
    '#weight' => 2,
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
    '#prefix' => "<div id='features-export-info'>",
    '#suffix' => '</div>',
  );
  $form['info']['name'] = array(
    '#title' => t('Name'),
    '#description' => t('Example: Image gallery') . ' (' . t('Do not begin name with numbers.') . ')',
    '#type' => 'textfield',
    '#default_value' => !empty($feature->info['name']) ? $feature->info['name'] : '',
    '#attributes' => array('class' => array('feature-name')),
  );
  $form['info']['module_name'] = array(
    '#type' => 'textfield',
    '#title' => t('Machine-readable name'),
    '#description' => t('Example: image_gallery') . '<br/>' . t('May only contain lowercase letters, numbers and underscores. <strong>Try to avoid conflicts with the names of existing Drupal projects.</strong>'),
    '#required' => TRUE,
    '#default_value' => !empty($feature->name) ? $feature->name : '',
    '#attributes' => array('class' => array('feature-module-name')),
    '#element_validate' => array('features_export_form_validate_field'),
  );
  // If recreating this feature, disable machine name field and blank out
  // js-attachment classes to ensure the machine name cannot be changed.
  if (isset($feature)) {
    $form['info']['module_name']['#value'] = $feature->name;
    $form['info']['module_name']['#disabled'] = TRUE;
    $form['info']['name']['#attributes'] = array();
  }
  $form['info']['description'] = array(
    '#title' => t('Description'),
    '#description' => t('Provide a short description of what users should expect when they enable your feature.'),
    '#type' => 'textfield',
    '#default_value' => !empty($feature->info['description']) ? $feature->info['description'] : '',
  );
  $form['info']['package'] = array(
    '#title' => t('Package'),
    '#description' => t('Organize your features in groups.'),
    '#type' => 'textfield',
    '#autocomplete_path' => 'features/autocomplete/packages',
    '#default_value' => !empty($feature->info['package']) ? $feature->info['package'] : 'Features',
  );
  $form['info']['version'] = array(
    '#title' => t('Version'),
    '#description' => t('Examples: 7.x-1.0, 7.x-1.0-beta1'),
    '#type' => 'textfield',
    '#required' => FALSE,
    '#default_value' => !empty($feature->info['version']) ? $feature->info['version'] : '',
    '#size' => 30,
    '#element_validate' => array('features_export_form_validate_field'),
  );
  $form['advanced'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced Options'),
    '#tree' => FALSE,
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#weight' => 10,
    '#prefix' => "<div id='features-export-advanced'>",
    '#suffix' => '</div>',
  );
  $form['advanced']['project_status_url'] = array(
    '#title' => t('URL of update XML'),
    '#description' => t('URL of Feature Server.  For Example: http://mywebsite.com/fserver'),
    '#type' => 'textfield',
    '#required' => FALSE,
    '#default_value' => !empty($feature->info['project status url']) ? $feature->info['project status url'] : '',
    '#element_validate' => array('features_export_form_validate_field'),
  );

  // build the Component Listing panel on the right
  _features_export_form_components($form, $form_state);

  $form['buttons'] = array(
    '#theme' => 'features_form_buttons',
    '#tree' => FALSE,
    '#weight' => 99,
    '#prefix' => "<div id='features-export-buttons'>",
    '#suffix' => '</div>',
  );
  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Download feature'),
    '#weight' => 10,
    '#submit' => array('features_export_build_form_submit'),
  );

  return $form;
}

/**
 * Return the render array elements for the Components selection on the Export form
 * @param  array $feature    - feature associative array
 * @param  array $components - array of components in feature
 */
function _features_export_form_components(&$form, &$form_state) {
  drupal_add_css(drupal_get_path('module', 'features') . '/features.css');
  drupal_add_js(drupal_get_path('module', 'features') . '/features.js');

  $feature = $form['#feature'];

  $form['export'] = array(
    '#type' => 'fieldset',
    '#title' => t('Components'),
    '#description' => t('Expand each component section and select which items should be included in this feature export.'),
    '#tree' => FALSE,
    '#prefix' => "<div id='features-export-wrapper'>",
    '#suffix' => '</div>',
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
    '#weight' => 1,
  );

  // filter field used in javascript, so javascript will unhide it
  $form['export']['features_filter'] = array(
    '#type' => 'textfield',
    '#title' => t('Filter'),
    '#default_value' => '',
    '#hidden' => TRUE,
    '#prefix' => "<div id='features-filter' class='element-invisible'>",
    '#suffix' => "<span class='features-filter-clear'>" . t('Clear') .
      " </span></div>",
  );

  // this refresh button will rebuild the form.
  // this button is hidden by javascript since it is only needed when
  // javascript is not available
  $form['export']['features_refresh'] = array(
    '#type' => 'submit',
    '#value' => t('Refresh'),
    '#name' => 'features-refresh',
    '#attributes' => array(
      'title' => t("Refresh the list of auto-detected items."),
      'class' => array('features-refresh-button'),
    ),
    '#weight' => -1,
    '#submit' => array('features_export_form_rebuild'),
    '#prefix' => "<div class='features-refresh-wrapper'>",
    '#suffix' => "</div>",
    '#ajax' => array(
      'callback' => 'features_export_form_ajax',
      'wrapper' => 'features-export-wrapper',
      'event' => 'refresh_components',
    ),
  );
  // generate the export array for the current feature and user selections
  $export = _features_export_build($feature, $form_state);

  $sections = array('included', 'detected', 'added');
  foreach ($export['components'] as $component => $component_info) {
    $label = (isset($component_info['name']) ?
      $component_info['name'] . " <span>(" . check_plain($component) . ")</span>" : check_plain($component));

    $count = 0;
    foreach ($sections as $section) {
      $count += count($component_info['options'][$section]);
    }
    $extra_class = ($count == 0) ? 'features-export-empty' : '';
    $component_name = str_replace('_', '-', check_plain($component));

    if ($count + count($component_info['options']['sources']) > 0) {

      $form['export'][$component] = array(
        '#markup' => '',
        '#tree' => TRUE,
        );
      $form['export'][$component]['sources'] = array(
        '#type' => 'fieldset',
        '#title' => $label,
        '#tree' => TRUE,
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#attributes' => array('class' => array('features-export-component')),
        '#prefix' => "<div class='features-export-parent component-$component'>",
      );

      $form['export'][$component]['sources']['selected'] = array(
        '#type' => 'checkboxes',
        '#id' => "edit-sources-$component_name",
        '#options' => features_dom_encode_options($component_info['options']['sources']),
        '#default_value' => features_dom_encode_options($component_info['selected']['sources'], FALSE),
        '#attributes' => array(
          'class' => array('component-select'),
        ),
      );

      foreach ($sections as $section) {
        $form['export'][$component][$section] = array(
          '#type' => 'checkboxes',
          '#options' => !empty($component_info['options'][$section]) ?
            features_dom_encode_options($component_info['options'][$section]) : array(),
          '#default_value' => !empty($component_info['selected'][$section]) ?
            features_dom_encode_options($component_info['selected'][$section], FALSE) : array(),
          '#attributes' => array('class' => array('component-' . $section)),
        );
      }
      $form['export'][$component][$sections[0]]['#prefix'] =
        "<div class='component-list features-export-list $extra_class'>";
      $form['export'][$component][$sections[count($sections)-1]]['#suffix'] = '</div></div>';
    }
  }
}

/**
 * Return the full feature export array based upon user selections in form_state
 * @param  array $feature    Feature array to be exported
 * @param  array $form_state Optional form_state information for user selections
 *   can be updated to reflect new selection status
 * @return array             New export array to be exported
 *   array['components'][$component_name] = $component_info
 *     $component_info['options'][$section] is list of available options
 *     $component_info['selected'][$section] is option state TRUE/FALSE
 *   $section = array('sources', included', 'detected', 'added')
 *     sources - options that are available to be added to the feature
 *     included - options that have been previously exported to the feature
 *     detected - options that have been auto-detected
 *     added - newly added options to the feature
 *
 * NOTE: This routine gets a bit complex to handle all of the different possible
 * user checkbox selections and de-selections.
 * Cases to test:
 *   1a) uncheck Included item -> mark as Added but unchecked
 *   1b) re-check unchecked Added item -> return it to Included check item
 *   2a) check Sources item -> mark as Added and checked
 *   2b) uncheck Added item -> return it to Sources as unchecked
 *   3a) uncheck Included item that still exists as auto-detect -> mark as Detected but unchecked
 *   3b) re-check Detected item -> return it to Included and checked
 *   4a) check Sources item should also add any auto-detect items as Detected and checked
 *   4b) uncheck Sources item with auto-detect and auto-detect items should return to Sources and unchecked
 *   5a) uncheck a Detected item -> refreshing page should keep it as unchecked Detected
 *   6)  when nothing changes, refresh should not change any state
 *   7)  should never see an unchecked Included item
 */
function _features_export_build($feature, &$form_state) {
  module_load_include('inc', 'features', 'features.export');
  features_include();

  $components = features_get_components();
  uasort($components, 'features_compare_component_name');

  // Assemble the combined component list
  $stub = array();
  $sections = array('sources', 'included', 'detected', 'added');

  // create a new feature "stub" to populate
  foreach ($components as $component => $component_info) {
    // User-selected components take precedence.
    $stub[$component] = array();
    // add selected items from Sources checkboxes
    if (!empty($form_state['values'][$component]['sources']['selected'])) {
      $stub[$component] = array_merge($stub[$component], features_dom_decode_options(array_filter($form_state['values'][$component]['sources']['selected'])));
    }
    // add selected items from already Included and newly Added checkboxes
    foreach (array('included', 'added') as $section) {
      if (!empty($form_state['values'][$component][$section])) {
        $stub[$component] = array_merge($stub[$component], features_dom_decode_options(array_filter($form_state['values'][$component][$section])));
      }
    }
    // Only fallback to an existing feature's values if there are no export options for the component.
    if (empty($stub[$component]) && !empty($feature->info['features'][$component])) {
      $stub[$component] = $feature->info['features'][$component];
    }
  }
  // Generate new populated feature
  $feature_name = isset($feature->name) ? $feature->name : NULL;
  $export = features_populate(array('features' => $stub, 'dependencies' => $stub['dependencies']), $feature_name);

  // Components that are already exported to feature
  $exported_features_info = !empty($feature->info['features']) ? $feature->info['features'] : array();
  $exported_features_info['dependencies'] = !empty($feature->info['dependencies']) ? $feature->info['dependencies'] : array();
  // Components that should be exported
  $new_features_info = !empty($export['features']) ? $export['features'] : array();
  $new_features_info['dependencies'] = !empty($export['dependencies']) ? $export['dependencies'] : array();
  $excluded = !empty($feature->info['features_exclude']) ? $feature->info['features_exclude'] : array();

  // now fill the $export with categorized sections of component options
  // based upon user selections and de-selections
  foreach ($components as $component => $component_info) {
    $component_export = $component_info;
    foreach ($sections as $section) {
      $component_export['options'][$section] = array();
      $component_export['selected'][$section] = array();
    }
    $options = features_invoke($component, 'features_export_options');
    if (!empty($options)) {
      $exported_components = !empty($exported_features_info[$component]) ? $exported_features_info[$component] : array();
      $new_components = !empty($new_features_info[$component]) ? $new_features_info[$component] : array();

      // Find all default components that are not provided by this feature and
      // strip them out of the possible options.
      if ($map = features_get_default_map($component)) {
        foreach ($map as $k => $v) {
          if (isset($options[$k]) && (!isset($feature->name) || $v !== $feature->name)) {
            unset($options[$k]);
          }
        }
      }
      foreach ($options as $key => $value) {
        // if checkbox in Sources is checked, move it to Added section
        if (!empty($form_state['values'][$component]['sources']['selected'][$key])) {
          unset($form_state['input'][$component]['sources']['selected'][$key]);
          $form_state['values'][$component]['sources']['selected'][$key] = FALSE;
          $form_state['values'][$component]['added'][$key] = 1;
          $form_state['input'][$component]['added'][$key] = features_dom_encode($key);
          $component_export['options']['added'][$key] = check_plain($value);
          $component_export['selected']['added'][$key] = $key;
        }
        elseif (in_array($key, $new_components)) {
          // option is in the New exported array
          if (in_array($key, $exported_components)) {
            // option was already previously exported
            // so it's part of the Included checkboxes
            $section = 'included';
            $default_value = $key;
            // unless Included item was un-selected (removed from export $stub)
            // but was re-detected in the $new_components
            // means it was an auto-detect that was previously part of the export
            // and is now de-selected in UI
            if (!empty($form_state['values']) &&
                (isset($form_state['values'][$component]['included'][$key]) ||
                 empty($form_state['values'][$component]['detected'][$key])) &&
                empty($stub[$component][$key])) {
              $section = 'detected';
              $default_value = FALSE;

            }
            // unless it's unchecked in the form, then move it to Newly disabled item
            elseif (!empty($form_state['values']) &&
                empty($form_state['values'][$component]['added'][$key]) &&
                empty($form_state['values'][$component]['detected'][$key]) &&
                empty($form_state['values'][$component]['included'][$key])) {
              $section = 'added';
              $default_value = FALSE;
            }
          }
          else {
            // option was in New exported array, but NOT in already exported
            // so it's an auto-detect item
            $section = 'detected';
            if (isset($excluded[$component][$key]) && !isset($form_state['values'][$component]['detected'][$key])) {
              $default_value = FALSE;
            }
            else {
              $default_value = $key;
            }
            // if it's already checked in Added or Sources, leave it in Added as checked
            if (!empty($form_state['values']) &&
              (!empty($form_state['values'][$component]['added'][$key]) ||
               !empty($form_state['values'][$component]['sources']['selected'][$key]))) {
              $section = 'added';
              $default_value = $key;
            }
            // if it's already been unchecked, leave it unchecked
            elseif (!empty($form_state['values']) &&
              !isset($form_state['values'][$component]['sources']['selected'][$key]) &&
              empty($form_state['values'][$component]['detected'][$key]) &&
              !isset($form_state['values'][$component]['added'][$key])) {
              $section = 'detected';
              $default_value = FALSE;
            }
          }
          $component_export['options'][$section][$key] = check_plain($value);
          $component_export['selected'][$section][$key] = $default_value;
          // save which items are specifically excluded from auto-detection
          if (($section == 'detected') && ($default_value === FALSE)) {
            $excluded[$component][$key] = $key;
          }
          else {
            unset($excluded[$component][$key]);
          }
          // remove the 'input' and set the 'values' so Drupal stops looking at 'input'
          if (isset($form_state['values'])) {
            if (!$default_value) {
              unset($form_state['input'][$component][$section][$key]);
              $form_state['values'][$component][$section][$key] = FALSE;
            }
            else {
              $form_state['input'][$component][$section][$key] = features_dom_encode($key);
              $form_state['values'][$component][$section][$key] = 1;
            }
          }
        }
        else {
          // option was not part of the new export
          $added = FALSE;
          foreach (array('included', 'added') as $section) {
            // restore any user-selected checkboxes
            if (!empty($form_state['values'][$component][$section][$key])) {
              $component_export['options'][$section][$key] = check_plain($value);
              $component_export['selected'][$section][$key] = $key;
              $added = TRUE;
            }
          }
          if (!$added) {
            // if not Included or Added, then put it back in the unchecked Sources checkboxes
            $component_export['options']['sources'][$key] = check_plain($value);
            $component_export['selected']['sources'][$key] = FALSE;
          }
        }
      }
    }
    $export['components'][$component] = $component_export;
    $export['features_exclude'] = $excluded;
  }
  return $export;
}

/**
 * AJAX callback for features_export_form.
 */
function features_export_form_ajax($form, &$form_state) {
  return $form['export'];
}

/**
 * Tells the ajax form submission to rebuild form state.
 */
function features_export_form_rebuild($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
}

function features_export_components_json($feature_name) {
  module_load_include('inc', 'features', 'features.export');
  $export = array();
  if (!empty($_POST['items'])) {
    $stub = array();
    foreach ($_POST['items'] as $key) {
      preg_match('/^([^\[]+)(\[.+\])?\[(.+)\]\[(.+)\]$/', $key, $matches);
      if (!empty($matches[1]) && !empty($matches[4])) {
        $component = $matches[1];
        $item = $matches[4];
        if (empty($stub[$component])) {
          $stub[$component] = array($item);
        }
        else {
          $stub[$component] = array_merge($stub[$component], array($item));
        }
      }
    }
    $export = features_populate(array('features' => $stub, 'dependencies' => $stub['dependencies']), $feature_name);
    $export['features']['dependencies'] = $export['dependencies'];
  }
  print drupal_json_encode($export['features']);
}
/**
 * Render API callback: Validates a project field.
 *
 * This function is assigned as an #element_validate callback in
 * features_export_form().
 */
function features_export_form_validate_field($element, &$form_state) {
  switch ($element['#name']) {
    case 'module_name':
      if (!preg_match('!^[a-z0-9_]+$!', $element['#value'])) {
        form_error($element, t('The machine-readable name must contain only lowercase letters, numbers, and underscores.'));
      }
      // If user is filling out the feature name for the first time and uses
      // the name of an existing module throw an error.
      else if (empty($element['#default_value']) && features_get_info('module', $element['#value'])) {
        form_error($element, t('A module by the name @name already exists on your site. Please choose a different name.', array('@name' => $element['#value'])));
      }
      break;
    case 'project_status_url':
      if (!empty($element['#value']) && !valid_url($element['#value'])) {
        form_error($element, t('The URL %url is invalid. Please enter a fully-qualified URL, such as http://www.example.com/feed.xml.', array('%url' => $element['#value'])));
      }
      break;
    case 'version':
      preg_match('/^(?P<core>\d+\.x)-(?P<major>\d+)\.(?P<patch>\d+)-?(?P<extra>\w+)?$/', $element['#value'], $matches);
      if (!empty($element['#value']) && !isset($matches['core'], $matches['major'])) {
        form_error($element, t('Please enter a valid version with core and major version number. Example: @example', array('@example' => '7.x-1.0')));
      };
      break;
  }
}

/**
 * Form submission handler for features_export_form().
 */
function features_export_build_form_submit($form, &$form_state) {
  // generate the selected export
  $export = _features_export_build($form['#feature'], $form_state);
  unset($export['components']); // remove the UI data that we are not saving to disk

  $module_name = $form_state['values']['module_name'];
  // Directly copy the following attributes
  $attr = array('name', 'description', 'package');
  foreach ($attr as $key) {
    $export[$key] = isset($form_state['values'][$key]) ? $form_state['values'][$key] : NULL;
  }
  // If either update status-related keys are provided, add a project key
  // corresponding to the module name.
  if (!empty($form_state['values']['version']) || !empty($form_state['values']['project_status_url'])) {
    $export['project'] = $form_state['values']['module_name'];
  }
  if (!empty($form_state['values']['version'])) {
    $export['version'] = $form_state['values']['version'];
  }
  if (!empty($form_state['values']['project_status_url'])) {
    $export['project status url'] = $form_state['values']['project_status_url'];
  }

  // Generate download
  if ($files = features_export_render($export, $module_name, TRUE)) {
    $filename = (!empty($export['version']) ? "{$module_name}-{$export['version']}" : $module_name) . '.tar';

    // Clear out output buffer to remove any garbage from tar output.
    if (ob_get_level()) {
      ob_end_clean();
    }

    drupal_add_http_header('Content-type', 'application/x-tar');
    drupal_add_http_header('Content-Disposition', 'attachment; filename="'. $filename .'"');
    drupal_send_headers();

    $tar = array();
    $filenames = array();
    foreach ($files as $extension => $file_contents) {
      if (!in_array($extension, array('module', 'info'))) {
        $extension .= '.inc';
      }
      $filenames[] = "{$module_name}.$extension";
      print features_tar_create("{$module_name}/{$module_name}.$extension", $file_contents);
    }
    if (features_get_modules($module_name, TRUE)) {
      $module_path = drupal_get_path('module', $module_name);
      // file_scan_directory() can throw warnings when using PHP 5.3, messing
      // up the output of our file stream. Suppress errors in this one case in
      // order to produce valid output.
      foreach (@file_scan_directory($module_path, '/.*/') as $file) {
        $filename = substr($file->uri, strlen($module_path) + 1);
        if (!in_array($filename, $filenames)) {
          // Add this file.
          $contents = file_get_contents($file->uri);
          print features_tar_create("{$module_name}/{$filename}", $contents);
          unset($contents);
        }
      }
    }
    print pack("a1024","");
    exit;
  }
}

/**
 * array_filter() callback for excluding hidden modules.
 */
function features_filter_hidden($module) {
  return empty($module->info['hidden']);
}

/**
 * Form constructor for the features configuration form.
 */
function features_admin_form($form, $form_state) {
  // Load export functions to use in comparison.
  module_load_include('inc', 'features', 'features.export');

  // Clear & rebuild key caches
  features_get_info(NULL, NULL, TRUE);
  features_rebuild();

  $modules = array_filter(features_get_modules(), 'features_filter_hidden');
  $features = array_filter(features_get_features(), 'features_filter_hidden');
  $conflicts = features_get_conflicts();

  foreach ($modules as $key => $module) {
    if ($module->status && !empty($module->info['dependencies'])) {
      foreach ($module->info['dependencies'] as $dependent) {
        if (isset($features[$dependent])) {
          $features[$dependent]->dependents[$key] = $module->info['name'];
        }
      }
    }
  }

  if ( empty($features) ) {
    $form['no_features'] = array(
      '#markup' => t('No Features were found. Please use the !create_link link to create
      a new Feature module, or upload an existing Feature to your modules directory.',
      array('!create_link' => l(t('Create Feature'), 'admin/structure/features/create'))),
    );
    return $form ;
  }

  $form = array('#features' => $features);

  // Generate features form. Features are sorted by dependencies, resort alpha
  ksort($features);
  foreach ($features as $name => $module) {
    $package_title = !empty($module->info['package']) ? $module->info['package'] : t('Other');
    $package = strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', $package_title));

    // Set up package elements
    if (!isset($form[$package])) {
      $form[$package] = array(
        '#tree' => FALSE,
        '#title' => check_plain($package_title),
        '#theme' => 'features_form_package',
        '#type' => 'fieldset',
        '#group' => 'packages',
      );
      $form[$package]['links'] =
      $form[$package]['version'] =
      $form[$package]['weight'] =
      $form[$package]['status'] =
      $form[$package]['action'] = array('#tree' => TRUE);
    }

    $disabled = FALSE;
    $description = isset($module->info['description']) ? $module->info['description'] : '';

    // Detect unmet dependencies
    if (!empty($module->info['dependencies'])) {
      $unmet_dependencies = array();
      $dependencies = _features_export_maximize_dependencies($module->info['dependencies']);
      foreach ($dependencies as $dependency) {
        if (empty($modules[$dependency])) {
          $unmet_dependencies[] = theme('features_module_status', array('status' => FEATURES_MODULE_MISSING, 'module' => $dependency));
        }
      }
      if (!empty($unmet_dependencies)) {
        $description .= "<div class='dependencies'>" . t('Unmet dependencies: @dependencies', array('@dependencies' => implode(', ', $unmet_dependencies))) . "</div>";
        $disabled = TRUE;
      }
    }

    if (!empty($module->dependents)) {
      $disabled = TRUE;
      $description .= "<div class='requirements'>". t('Required by: @dependents', array('@dependents' => implode(', ', $module->dependents))) ."</div>";
    }

    // Detect potential conflicts
    if (!empty($conflicts[$name])) {
      $module_conflicts = array();
      foreach (array_keys($conflicts[$name]) as $conflict) {
        // If conflicting module is disabled, indicate so in feature listing
        $status = !module_exists($conflict) ? FEATURES_MODULE_DISABLED : FEATURES_MODULE_CONFLICT;
        $module_conflicts[] = theme('features_module_status', array('status' => $status, 'module' => $conflict));
        // Only disable modules with conflicts if they are not already enabled.
        // If they are already enabled, somehow the user got themselves into a
        // bad situation and they need to be able to disable a conflicted module.
        if (module_exists($conflict) && !module_exists($name)) {
          $disabled = TRUE;
        }
      }
      $description .= "<div class='conflicts'>". t('Conflicts with: @conflicts', array('@conflicts' => implode(', ', $module_conflicts))) ."</div>";
    }

    $href = "admin/structure/features/{$name}";
    $module_name = (user_access('administer features')) ? l($module->info['name'], $href) : $module->info['name'];
    $form[$package]['status'][$name] = array(
      '#type' => 'checkbox',
      '#title' => $module_name,
      '#description' => check_plain($description),
      '#default_value' => $module->status,
      '#disabled' => $disabled,
    );

    if (!empty($module->info['project status url'])) {
      $uri = l(truncate_utf8($module->info['project status url'], 35, TRUE, TRUE), $module->info['project status url']);
    }
    else if (isset($module->info['project'], $module->info['version'], $module->info['datestamp'])) {
      $uri = l('http://drupal.org', 'http://drupal.org/project/' . $module->info['project']);
    }
    else {
      $uri = t('Unavailable');
    }
    $version = !empty($module->info['version']) ? $module->info['version'] : '';
    $version = !empty($version) ? "<div class='description'>$version</div>" : '';
    $form[$package]['sign'][$name] = array('#markup' => "{$uri} {$version}");

    if (user_access('administer features')) {
      // Add status link
      if ($module->status) {
        $state = theme('features_storage_link', array('storage' => FEATURES_CHECKING, 'path' => $href));
        $state .= l(t('Check'), "admin/structure/features/{$name}/status", array('attributes' => array('class' => array('admin-check'))));
        $state .= theme('features_storage_link', array('storage' => FEATURES_REBUILDING, 'path' => $href));
        $state .= theme('features_storage_link', array('storage' => FEATURES_NEEDS_REVIEW, 'path' =>  $href));
        $state .= theme('features_storage_link', array('storage' => FEATURES_OVERRIDDEN, 'path' =>  $href));
        $state .= theme('features_storage_link', array('storage' => FEATURES_DEFAULT, 'path' =>  $href));
      }
      elseif (!empty($conflicts[$name])) {
        $state = theme('features_storage_link', array('storage' => FEATURES_CONFLICT, 'path' => $href));
      }
      else {
        $state = theme('features_storage_link', array('storage' => FEATURES_DISABLED, 'path' => $href));
      }
      $form[$package]['state'][$name] = array(
        '#markup' => !empty($state) ? $state : '',
      );

      // Add in recreate link
      $form[$package]['actions'][$name] = array(
        '#markup' => l(t('Recreate'), "admin/structure/features/{$name}/recreate", array('attributes' => array('class' => array('admin-update')))),
      );
    }
  }
  ksort($form);

  // As of 7.0 beta 2 it matters where the "vertical_tabs" element lives on the
  // the array. We add it late, but at the beginning of the array because that
  // keeps us away from trouble.
  $form = array('packages' => array('#type' => 'vertical_tabs')) + $form;

  $form['buttons'] = array(
    '#theme' => 'features_form_buttons',
  );
  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save settings'),
    '#submit' => array('features_form_submit'),
    '#validate' => array('features_form_validate'),
  );
  return $form;
}

/**
 * Display the components of a feature.
 */
function features_admin_components($form, $form_state, $feature) {
  // Breadcrumb navigation
  $breadcrumb[] = l(t('Home'), NULL);
  $breadcrumb[] = l(t('Features'), 'admin/structure/features');
  drupal_set_breadcrumb($breadcrumb);

  module_load_include('inc', 'features', 'features.export');
  $form = array();

  // Store feature info for theme layer.
  $form['module'] = array('#type' => 'value', '#value' => $feature->name);
  $form['#info'] = $feature->info;
  $form['#dependencies'] = array();
  if (!empty($feature->info['dependencies'])) {
    foreach ($feature->info['dependencies'] as $dependency) {
      $parsed_dependency = drupal_parse_dependency($dependency);
      $dependency = $parsed_dependency['name'];
      $status = features_get_module_status($dependency);
      $form['#dependencies'][$dependency] = $status;
    }
  }

  $conflicts = features_get_conflicts();
  if (!module_exists($form['module']['#value']) && isset($form['module']['#value']) && !empty($conflicts[$form['module']['#value']])) {
    $module_conflicts = $conflicts[$form['module']['#value']];
    $conflicts = array();
    foreach ($module_conflicts as $conflict) {
      $conflicts = array_merge_recursive($conflict, $conflicts);
    }
  }
  else {
    $conflicts = array();
  }
  $form['#conflicts'] = $conflicts;

  $review = $revert = FALSE;

  // Iterate over components and retrieve status for display
  $states = features_get_component_states(array($feature->name), FALSE);
  $form['revert']['#tree'] = TRUE;
  foreach ($feature->info['features'] as $component => $items) {
    if (user_access('administer features') && array_key_exists($component, $states[$feature->name]) && in_array($states[$feature->name][$component], array(FEATURES_OVERRIDDEN, FEATURES_NEEDS_REVIEW))) {
      switch ($states[$feature->name][$component]) {
        case FEATURES_OVERRIDDEN:
          $revert = TRUE;
          break;
        case FEATURES_NEEDS_REVIEW:
          $review = TRUE;
          break;
      }
      $form['revert'][$component] = array(
        '#type' => 'checkbox',
        '#default_value' => FALSE,
      );
    }
    if (module_exists('diff')) {
      $diffpath = "admin/structure/features/{$feature->name}/diff/{$component}";
      $item = menu_get_item($diffpath);
      $path = ($item && $item['access']) ? $diffpath : NULL;
    }
    else {
      $path = NULL;
    }

    $storage = FEATURES_DEFAULT;
    if (array_key_exists($component, $states[$feature->name])) {
      $storage = $states[$feature->name][$component];
    }
    else if (array_key_exists($component, $conflicts)) {
      $storage = FEATURES_CONFLICT;
    }
    $form['components'][$component] = array(
      '#markup' => theme('features_storage_link', array('storage' => $storage, 'path' =>  $path)),
    );
  }

  if ($review || $revert) {
    $form['buttons'] = array('#theme' => 'features_form_buttons', '#tree' => TRUE);
    if ($revert || $review) {
      $form['buttons']['revert'] = array(
        '#type' => 'submit',
        '#value' => t('Revert components'),
        '#submit' => array('features_admin_components_revert'),
      );
    }
    if ($review) {
      $form['buttons']['review'] = array(
        '#type' => 'submit',
        '#value' => t('Mark as reviewed'),
        '#submit' => array('features_admin_components_review'),
      );
    }
  }
  return $form;
}

/**
 * Submit handler for revert form.
 */
function features_admin_components_revert(&$form, &$form_state) {
  module_load_include('inc', 'features', 'features.export');
  features_include();
  $module = $form_state['values']['module'];
  $revert = array();
  foreach (array_filter($form_state['values']['revert']) as $component => $status) {
    $revert[$module][] = $component;
    drupal_set_message(t('Reverted all <strong>@component</strong> components for <strong>@module</strong>.', array('@component' => $component, '@module' => $module)));
  }
  features_revert($revert);
  $form_state['redirect'] = 'admin/structure/features/' . $module;
}

/**
 * Submit handler for revert form.
 */
function features_admin_components_review(&$form, &$form_state) {
  module_load_include('inc', 'features', 'features.export');
  features_include();
  $module = $form_state['values']['module'];
  $revert = array();
  foreach (array_filter($form_state['values']['revert']) as $component => $status) {
    features_set_signature($module, $component);
    drupal_set_message(t('All <strong>@component</strong> components for <strong>@module</strong> reviewed.', array('@component' => $component, '@module' => $module)));
  }
  $form_state['redirect'] = 'admin/structure/features/' . $module;
}

/**
 * Validate handler for the 'manage features' form.
 */
function features_form_validate(&$form, &$form_state) {
  include_once './includes/install.inc';
  $conflicts = features_get_conflicts();
  foreach ($form_state['values']['status'] as $module => $status) {
    if ($status) {
      if (!empty($conflicts[$module])) {
        foreach (array_keys($conflicts[$module]) as $conflict) {
          if (!empty($form_state['values']['status'][$conflict])) {
            form_set_error('status', t('The feature @module cannot be enabled because it conflicts with @conflict.', array('@module' => $module, '@conflict' => $conflict)));
          }
        }
      }
      if (!drupal_check_module($module)) {
        form_set_error('status', t('The feature @module cannot be enabled because it has unmet requirements.', array('@module' => $module)));
      }
    }
  }
}

/**
 * Submit handler for the 'manage features' form
 */
function features_form_submit(&$form, &$form_state) {
  // Clear drupal caches after enabling a feature. We do this in a separate
  // page callback rather than as part of the submit handler as some modules
  // have includes/other directives of importance in hooks that have already
  // been called in this page load.
  $form_state['redirect'] = 'admin/structure/features/cleanup/clear';

  $features = $form['#features'];
  if (!empty($features)) {
    $status = $form_state['values']['status'];
    $install = array_keys(array_filter($status));
    $disable = array_diff(array_keys($status), $install);

    // Disable first. If there are any features that are disabled that are
    // dependencies of features that have been queued for install, they will
    // be re-enabled.
    module_disable($disable);
    features_install_modules($install);
  }
}

/**
 * Form for disabling orphaned dependencies.
 */
function features_cleanup_form($form, $form_state, $cache_clear = FALSE) {
  $form = array();

  // Clear caches if we're getting a post-submit redirect that requests it.
  if ($cache_clear) {
    drupal_flush_all_caches();

    // The following functions need to be run because drupal_flush_all_caches()
    // runs rebuilds in the wrong order. The node type cache is rebuilt *after*
    // the menu is rebuilt, meaning that the menu tree is stale in certain
    // circumstances after drupal_flush_all_caches(). We rebuild again.
    menu_rebuild();
  }

  // Retrieve orphaned modules and provide them as optional modules to be disabled.
  // Exclude any modules that have been added to the 'ignored' list.
  $options = array();
  $orphans = features_get_orphans();
  $ignored = variable_get('features_ignored_orphans', array());
  if (!empty($orphans)) {
    foreach ($orphans as $module) {
      if (!in_array($module->name, $ignored, TRUE)) {
        $options[$module->name] = check_plain($module->info['name']);
      }
    }
  }

  if (!empty($options)) {
    $form['orphans'] = array(
      '#title' => t('Orphaned dependencies'),
      '#description' => t('These modules are dependencies of features that have been disabled. They may be disabled without affecting other components of your website.'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => array_keys($options),
    );
    $form['buttons'] = array('#tree' => TRUE, '#theme' => 'features_form_buttons');
    $form['buttons']['disable'] = array(
      '#type' => 'submit',
      '#value' => t('Disable selected modules'),
      '#submit' => array('features_cleanup_form_disable'),
    );
    $form['buttons']['ignore'] = array(
      '#type' => 'submit',
      '#value' => t('Leave enabled'),
      '#submit' => array('features_cleanup_form_ignore'),
    );
  }
  else {
    drupal_goto('admin/structure/features');
  }
  return $form;
}

/**
 * Submit handler for disable action on features_cleanup_form().
 */
function features_cleanup_form_disable(&$form, &$form_state) {
  if (!empty($form_state['values']['orphans'])) {
    $disable = array_keys(array_filter($form_state['values']['orphans']));
    $ignored = array_diff(array_keys($form_state['values']['orphans']), $disable);

    // Disable any orphans that have been selected.
    module_disable($disable);
    drupal_flush_all_caches();

    // Add enabled modules to ignored orphans list.
    $ignored_orphans = variable_get('features_ignored_orphans', array());
    foreach ($ignored as $module) {
      $ignored_orphans[$module] = $module;
    }
    variable_set('features_ignored_orphans', $ignored_orphans);
  }
  $form_state['redirect'] = 'admin/structure/features/cleanup';
}

/**
 * Submit handler for ignore action on features_cleanup_form().
 */
function features_cleanup_form_ignore(&$form, &$form_state) {
  if (!empty($form_state['values']['orphans'])) {
    $ignored = array_keys($form_state['values']['orphans']);
    $ignored_orphans = variable_get('features_ignored_orphans', array());
    foreach ($ignored as $module) {
      $ignored_orphans[$module] = $module;
    }
    variable_set('features_ignored_orphans', $ignored_orphans);
  }
  $form_state['redirect'] = 'admin/structure/features/cleanup';
}

/**
 * Page callback to display the differences between what's in code and
 * what is in the db.
 *
 * @param $feature
 *   A loaded feature object to display differences for.
 * @param $component
 *   (optional) Specific component to display differences for. If excluded, all
 *   components are used.
 *
 * @return
 *   Themed display of what is different.
 */
function features_feature_diff($feature, $component = NULL) {
  drupal_add_css(drupal_get_path('module', 'features') . '/features.css');
  module_load_include('inc', 'features', 'features.export');

  $overrides = features_detect_overrides($feature);

  $output = '';
  if (!empty($overrides)) {
    // Filter overrides down to specified component.
    if (isset($component) && isset($overrides[$component])) {
      $overrides = array($component => $overrides[$component]);
    }

    module_load_include('inc', 'diff', 'diff.engine');
    $formatter = new DrupalDiffFormatter();

    $rows = array();
    foreach ($overrides as $component => $items) {
      $rows[] = array(array('data' => $component, 'colspan' => 4, 'header' => TRUE));
      $diff = new Diff(explode("\n", $items['default']), explode("\n", $items['normal']));
      $rows = array_merge($rows, $formatter->format($diff));
    }
    $header = array(
      array('data' => t('Default'), 'colspan' => 2),
      array('data' => t('Overrides'), 'colspan' => 2),
    );
    $output .= theme('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('class' => array('diff', 'features-diff'))));
  }
  else {
    $output = "<div class='features-empty'>" . t('No changes have been made to this feature.') . "</div>";
  }
  $output = array('page' => array('#markup' => "<div class='features-comparison'>{$output}</div>"));
  return $output;
}

/**
 * Compare the component names. Used to sort alphabetically.
 */
function features_compare_component_name($a, $b) {
  return strcasecmp($a['name'], $b['name']);
}

/**
 * Javascript callback that returns the status of a feature.
 */
function features_feature_status($feature) {
  module_load_include('inc', 'features', 'features.export');
  return drupal_json_output(array('storage' => features_get_storage($feature->name)));
}

/**
 * Make a Drupal options array safe for usage with jQuery DOM selectors.
 * Encodes known bad characters into __[ordinal]__ so that they may be
 * safely referenced by JS behaviors.
 */
function features_dom_encode_options($options = array(), $keys_only = TRUE) {
  $replacements = features_dom_encode_map();
  $encoded = array();
  foreach ($options as $key => $value) {
    $encoded[strtr($key, $replacements)] = $keys_only ? $value : strtr($value, $replacements);
  }
  return $encoded;
}

function features_dom_encode($key) {
  $replacements = features_dom_encode_map();
  return strtr($key, $replacements);
}

/**
 * Decode an array of option values that have been encoded by
 * features_dom_encode_options().
 */
function features_dom_decode_options($options, $keys_only = FALSE) {
  $replacements = array_flip(features_dom_encode_map());
  $encoded = array();
  foreach ($options as $key => $value) {
    $encoded[strtr($key, $replacements)] = $keys_only ? $value : strtr($value, $replacements);
  }
  return $encoded;
}

/**
 * Returns encoding map for decode and encode options.
 */
function features_dom_encode_map() {
  return array(
    ':' => '__' . ord(':') . '__',
    '/' => '__' . ord('/') . '__',
    ',' => '__' . ord(',') . '__',
    '.' => '__' . ord('.') . '__',
    '<' => '__' . ord('<') . '__',
    '>' => '__' . ord('>') . '__',
  );
}

/**
 * Page callback: Autocomplete field for features package.
 *
 * @param $search_string
 *   The char or string that user have written in autocomplete field,
 *   this is the string this function uses for filter.
 *
 * @see features_menu()
 */
function features_autocomplete_packages($search_string) {
  $matched_packages = array();
  //fetch all modules that are features and copy the package name into a new array.
  foreach (features_get_features(NULL, TRUE) as $value) {
    if (preg_match('/' . $search_string . '/i', $value->info['package'])) {
      $matched_packages[$value->info['package']] = $value->info['package'];
    }
  }
  //removes duplicated package, we wont a list of all unique packages.
  $matched_packages = array_unique($matched_packages);
  drupal_json_output($matched_packages);
}
