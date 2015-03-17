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
 *   description = @Translation("Detect and add existing package modules."),
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
    $existing = $this->featuresManager->getExistingPackages();

    foreach ($existing as $name => $info) {
      $this->featuresManager->initPackage($name, $info['name'], !empty($info['description']) ? $info['description'] : '');
      $config = $this->featuresManager->listExtensionConfig($info['module']);
      $this->featuresManager->assignConfigPackage($name, $config);
    }
  }

}
