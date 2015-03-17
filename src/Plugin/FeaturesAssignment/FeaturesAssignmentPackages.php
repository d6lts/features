<?php

/**
 * @file
 * Contains \Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentPackages.
 */

namespace Drupal\features\Plugin\FeaturesAssignment;

use Drupal\features\FeaturesAssignmentMethodBase;
use Drupal\features\FeaturesManagerInterface;

/**
 * Class for creating any existing packages from previously exported config.
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
      $this->featuresManager->initPackage($name, $info['name'], !empty($info['description']) ? $info['description'] : '');
    }
  }

}
