<?php

/**
 * @file
 * Administration functions for config_packager.module.
 */

use Drupal\Component\Utility\String;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Prepares variables for package assignment configuration form.
 *
 * @param array $variables
 *   An associative array containing:
 *   - form: A render element representing the form.
 */
function template_preprocess_config_packager_assignment_configure_form(&$variables) {
  $form =& $variables['form'];

  $header = array(
    t('Assignment method'),
    t('Description'),
    t('Enabled'),
    t('Weight'),
  );

  // If there is at least one operation enabled, show the operation column.
  if ($form['#show_operations']) {
    $header[] = t('Operations');
  }

  $table = array(
    '#type' => 'table',
    '#header' => $header,
    '#attributes' => array('id' => 'config-packager-assignment-methods'),
    '#tabledrag' => array(
      array(
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'assignment-method-weight',
      ),
    ),
  );

  foreach ($form['title'] as $id => $element) {
    // Do not take form control structures.
    if (is_array($element) && element_child($id)) {
      $table[$id]['#attributes']['class'][] = 'draggable';
      $table[$id]['#weight'] = $element['#weight'];

      $table[$id]['title'] = array(
        '#prefix' => '<strong>',
        $form['title'][$id],
        '#suffix' => '</strong>',
      );
      $table[$id]['description'] = $form['description'][$id];
      $table[$id]['enabled'] = $form['enabled'][$id];
      $table[$id]['weight'] = $form['weight'][$id];
      if ($form['#show_operations']) {
        $table[$id]['operation'] = $form['operation'][$id];
      }
      // Unset to prevent rendering along with children.
      unset($form['title'][$id]);
      unset($form['description'][$id]);
      unset($form['enabled'][$id]);
      unset($form['weight'][$id]);
      unset($form['operation'][$id]);
    }
  }

  $variables += array(
    'table' => $table,
    'children' => $form,
    'attributes' => new Attribute(),
  );
}

/**
 * Themes individual items in an item list.
 */
function theme_config_packager_items(array $variables) {
  $items = $variables['items'];

  $list = array();
  foreach ($items as $item) {
    $list[] = '<span class="config-packager-item" title="' . $item['name'] . '">' . $item['label'] . '</span>';
  }

  return '<span class="config-packager-item-list">' . implode(' ', $list) . '</span>';
}
