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
  public function assignPackages() {
    $profile = $this->featuresManager->getProfile();
    $existing = $this->featuresManager->getExistingPackages();
    foreach ($existing as $name => $info) {
      // The original machine_name_short was stored in the "features" key in the info file.
      $feature_name = $info['features'];
      if (is_array($feature_name)) {
        $feature_name = $feature_name['name'];
      }
      $this->featuresManager->initPackage($feature_name, $info['name'], !empty($info['description']) ? $info['description'] : '');
      // Set the *actual* full machine name from the module.
      $packages = $this->featuresManager->getPackages();
      $packages[$feature_name]['machine_name'] = $name;
      $packages[$feature_name]['info'] = $info;
      $config = $this->featuresManager->listExtensionConfig($name);
      $packages[$feature_name]['config_orig'] = $config;
      $this->featuresManager->setPackages($packages);
    }
  }

}
