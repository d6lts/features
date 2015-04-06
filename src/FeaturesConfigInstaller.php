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
  protected function findPreExistingConfiguration(StorageInterface $storage) {
    // Override Drupal\Core\Config\ConfigInstaller::findPreExistingConfiguration
    // Allow config that already exists coming from Features
    $manager = \Drupal::service('features.manager');
    $assigner = \Drupal::service('features_assigner');
    $assigner->assignConfigPackages();
    $features_data = $manager->getConfigCollection();

    $existing_configuration = array();
    // Gather information about all the supported collections.
    $collection_info = $this->configManager->getConfigCollectionInfo();

    foreach ($collection_info->getCollectionNames() as $collection) {
      $config_to_create = array_keys($this->getConfigToCreate($storage, $collection));
      $active_storage = $this->getActiveStorages($collection);
      foreach ($config_to_create as $config_name) {
        if ($active_storage->exists($config_name)) {
          // Test if config is part of a Feature package.
          if (!isset($features_data[$config_name]) || empty($features_data[$config_name]['package'])) {
            $existing_configuration[$collection][] = $config_name;
          }
        }
      }
    }
    return $existing_configuration;
  }

}
