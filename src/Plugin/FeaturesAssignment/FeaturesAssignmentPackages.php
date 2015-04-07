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
   * Search for a bundle that matches the $info.yml export, or create a new
   * bundle as needed.
   * @param $info
   * @return \Drupal\features\FeaturesBundleInterface
   */
  protected function findBundle($info) {
    $bundle = NULL;
    if (!empty($info['features']['bundle'])) {
      $bundle = $this->assigner->getBundle($info['features']['bundle']);
    }
    elseif (!empty($info['package'])) {
      $bundle = $this->assigner->findBundleByName($info['package']);
    }
    if (!isset($bundle) && (!empty($info['package']) || !empty($info['features']['bundle']))) {
      // Create the bundle if it doesn't exist yet.
      $bundle = $this->assigner->createBundle($info['package'], $info['features']['bundle']);
    }
    else {
      // Else, return default bundle.
      $bundle = $this->assigner->getBundle('');
    }
    return $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function assignPackages() {
    $existing = $this->featuresManager->getExistingPackages();
    foreach ($existing as $name => $info) {
      $this->featuresManager->initPackage($name, $info['name'], !empty($info['description']) ? $info['description'] : '');
      // Set the *actual* full machine name from the module.
      $package = $this->featuresManager->getPackage($name);
      $bundle = $this->findBundle($info);
      if (isset($bundle)) {
        $package['bundle'] = $bundle->getMachineName();
      }
      $package['info'] = $info;
      $config = $this->featuresManager->listExtensionConfig($name);
      $package['config_orig'] = $config;
      $this->featuresManager->savePackage($package);
    }
  }

}
