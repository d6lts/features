<?php

/**
 * @file
 * Contains \Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentExisting.
 */

namespace Drupal\features\Plugin\FeaturesAssignment;

use Drupal\features\FeaturesAssignmentMethodBase;
use Drupal\features\FeaturesManagerInterface;

/**
 * Class for assigning existing modules to packages.
 *
 * @Plugin(
 *   id = \Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentExisting::METHOD_ID,
 *   weight = -99,
 *   name = @Translation("Existing"),
 *   description = @Translation("Add exported config to existing packages."),
 * )
 */
class FeaturesAssignmentExisting extends FeaturesAssignmentMethodBase {

  /**
   * The package assignment method id.
   */
  const METHOD_ID = 'existing';

  /**
   * {@inheritdoc}
   */
  public function assignPackages() {
    $profile = $this->featuresManager->getProfile();
    $existing = $this->featuresManager->getExistingPackages(FALSE, $profile['machine_name']);
    foreach ($existing as $name => $info) {
      $config = $this->featuresManager->listExtensionConfig($name);
      // The 'features' key contains the short machine_name of the module.
      $this->featuresManager->assignConfigPackage($info['features'], $config);
    }
  }

}
