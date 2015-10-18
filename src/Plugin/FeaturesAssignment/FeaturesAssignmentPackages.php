<?php

/**
 * @file
 * Contains \Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentPackages.
 */

namespace Drupal\features\Plugin\FeaturesAssignment;

use Drupal\features\FeaturesAssignmentMethodBase;
use Drupal\features\FeaturesManagerInterface;

/**
 * Class for assigning existing modules to packages.
 *
 * @Plugin(
 *   id = \Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentPackages::METHOD_ID,
 *   weight = -99,
 *   name = @Translation("Packages"),
 *   description = @Translation("Detect and add existing package modules."),
 * )
 */
class FeaturesAssignmentPackages extends FeaturesAssignmentMethodBase {

  /**
   * The package assignment method id.
   */
  const METHOD_ID = 'packages';

  /**
   * {@inheritdoc}
   */
  public function assignPackages($force = FALSE) {
    $existing = $this->featuresManager->getExistingPackages();
    foreach ($existing as $name => $info) {
      $this->featuresManager->initPackageFromInfo($name, $info);

      if (!empty($info['features']['excluded']) || !empty($info['features']['required'])) {
        // Copy over package excluded settings, if any.
        if (!empty($info['features']['excluded'])) {
          $config_collection = $this->featuresManager->getConfigCollection();
          foreach ($info['features']['excluded'] as $config_name) {
            if (isset($config_collection[$config_name])) {
              $config_collection[$config_name]['package_excluded'][] = $name;
            }
          }
          $this->featuresManager->setConfigCollection($config_collection);
        }
        // Assign required components, if any.
        if (!empty($info['features']['required'])) {
          $this->featuresManager->assignConfigPackage($name, $info['features']['required']);
        }
      }
    }
  }

}
