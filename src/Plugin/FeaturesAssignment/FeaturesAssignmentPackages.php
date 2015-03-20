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
    $existing = $this->featuresManager->getExistingPackages();
    foreach ($existing as $name => $info) {
      // The original machine_name_short was stored in the "features" key in the info file.
      $this->featuresManager->initPackage($info['features'], $info['name'], !empty($info['description']) ? $info['description'] : '');
      // Set the *actual* full machine name from the module.
      $packages = $this->featuresManager->getPackages();
      $packages[$info['features']]['machine_name'] = $name;
      $this->featuresManager->setPackages($packages);
    }
  }

}
