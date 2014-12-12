<?php

/**
 * @file
 * Contains \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentExclude.
 */

namespace Drupal\config_packager\Plugin\ConfigPackagerAssignment;

use Drupal\component\Utility\Unicode;
use Drupal\config_packager\ConfigPackagerAssignmentMethodBase;
use Drupal\Core\Config\ExtensionInstallStorage;

/**
 * Class for assigning configuration to packages based on entity types .
 *
 * @Plugin(
 *   id = \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentExclude::METHOD_ID,
 *   weight = -5,
 *   name = @Translation("Exclude"),
 *   description = @Translation("Configuration items are excluded from packaging by various methods including by configuration type."),
 *   config_route_name = "config_packager.assignment_exclude"
 * )
 */
class ConfigPackagerAssignmentExclude extends ConfigPackagerAssignmentMethodBase {

  /**
   * The package assignment method id.
   */
  const METHOD_ID = 'exclude';

  /**
   * {@inheritdoc}
   */
  public function assignPackages() {
    $config_types = $this->configPackagerManager->getConfigTypes();
    $settings = $this->configFactory->get('config_packager.assignment');
    $config_collection = $this->configPackagerManager->getConfigCollection();

    // Exclude by configuration type.
    $exclude_types = $settings->get('exclude.types');
    if (!empty($exclude_types)) {
      foreach ($config_collection as $item_name => $item) {
        if (in_array($item['type'], $exclude_types)) {
          unset($config_collection[$item_name]);
        }
      }
    }

    // Exclude by already provided by modules.
    $packaged = $settings->get('exclude.packaged');
    if ($packaged) {
      $extension_config_storage = new ExtensionInstallStorage($this->configStorage);
      $install_list = $extension_config_storage->listAll();
      foreach ($install_list as $item_name) {
        unset($config_collection[$item_name]);
      }
    }
    $this->configPackagerManager->setConfigCollection($config_collection);
  }

}
