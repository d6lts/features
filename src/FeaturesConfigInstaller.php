<?php

/**
 * @file
 * Contains Drupal\features\FeaturesConfigInstaller.
 */

namespace Drupal\features;

use Drupal\Core\Config\ConfigInstaller;
use Drupal\Core\Config\FileStorage;
use Drupal\features\FeaturesManagerInterface;

class FeaturesConfigInstaller extends ConfigInstaller {

  /**
   * {@inheritdoc}
   */
  public function findPreExistingConfiguration($type, $name) {
    $manager = \Drupal::service('features.manager');
    if (($type == 'module') && $manager->isFeatureModule($name)) {
      // Allow a Features package to be enabled even if it's config conflicts.
      return array();
    }
    return parent::findPreExistingConfiguration($type, $name);
  }
}
