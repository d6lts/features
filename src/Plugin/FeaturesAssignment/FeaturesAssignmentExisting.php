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
 *   weight = 12,
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
   * Calls assignConfigPackage without allowing exceptions to abort us.
   *
   * @param string $name
   *   The name of a feature module.
   */
  protected function safeAssignConfig($name) {
    $config = $this->featuresManager->listExtensionConfig($name, $this->assigner->getBundle());
    try {
      $this->featuresManager->assignConfigPackage($name, $config);
    }
    catch (\Exception $exception) {
      \Drupal::logger('features')->error($exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function assignPackages($force = FALSE) {
    // Find all existing packages that are in the new packages collection.
    $existing = $this->featuresManager->getExistingPackages(FALSE, $this->assigner->getBundle());
    $packages = $this->featuresManager->getPackages();
    $existing = array_intersect_key($existing, $packages);

    // Assign config to enabled modules first.
    foreach ($existing as $name => $info) {
      if ($info['status'] == FeaturesManagerInterface::STATUS_ENABLED) {
        $this->safeAssignConfig($name);
      }
    }
    // Now assign to disabled modules.
    foreach ($existing as $name => $info) {
      if ($info['status'] != FeaturesManagerInterface::STATUS_ENABLED) {
        $this->safeAssignConfig($name);
      }
    }
  }

}
