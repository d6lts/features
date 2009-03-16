<?php
// $Id$

/**
 * Implementation of hook_features_export().
 */
function block_features_export($data, &$export) {
  $pipe = array();
  foreach ($data as $bid) {
    $split = explode('-', $bid);
    $module = array_shift($split);
    $delta = implode('-', $split);

    $export['dependencies'][$module] = $module;

    switch ($module) {
      case 'views':
        if (strlen($delta) == 32) {
          $hashes = variable_get('views_block_hashes', array());
          if (!empty($hashes[$delta])) {
            $delta = $hashes[$delta];
          }
        }

        $delta_split = explode('-', $delta);
        $view_name = $delta_split[0];

        $pipe['views'][] = $view_name;
        break;
    }
  }
  return $pipe;
}
