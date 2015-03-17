<?php

/**
 * @file
 * Contains Drupal\config_share\ConfigShareConfigInstaller.
 */

namespace Drupal\features;

use Drupal\Core\Config\ConfigInstaller;
use Drupal\Core\Config\FileStorage;

class FeaturesConfigInstaller extends ConfigInstaller {

  /**
   * {@inheritdoc}
   */
  public function findPreExistingConfiguration($type, $name) {
    $module_path = drupal_get_path('module', $name);
    $info_file_uri = "$module_path/$name.info." . FileStorage::getFileExtension();
    $info = \Drupal::service('info_parser')->parse($info_file_uri);
    if (isset($info['features'])) {
      // allow a Features package to be enabled even if it's config conflicts
      return array();
    }
    return parent::findPreExistingConfiguration($type, $name);
  }
}
