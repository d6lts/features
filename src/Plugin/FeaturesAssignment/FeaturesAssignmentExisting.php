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
      // The original machine_name_short was stored in the "features" key in the info file.
      $feature_name = $info['features'];
      if (is_array($feature_name)) {
        $feature_name = $feature_name['name'];
      }
      $config = $this->featuresManager->listExtensionConfig($name);
      $this->featuresManager->assignConfigPackage($feature_name, $config);
    }
  }

}
