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

  protected function getPrefix($name, $short_name) {
    $pos = strpos($name, '_' . $short_name);
    $expected_pos = strlen($name) - strlen($short_name) - 1;
    if ($pos === $expected_pos) {
      return substr($name, 0, $expected_pos);
    }
    return '';
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
      $package['machine_name_short'] = $info['features']['name'];
      $bundle = NULL;
      if (!empty($info['package'])) {
        $bundle = $this->assigner->findBundleByName($info['package']);
        if (!isset($bundle)) {
          $bundle = $this->assigner->createBundle($info['package']);
          $prefix = $this->getPrefix($package['machine_name'], $package['machine_name_short']);
          if (!empty($prefix) && ($prefix != $bundle->getMachineName())) {
            // Update newly created bundle with correct machine name.
            $bundle->setMachineName($prefix);
            $this->assigner->setBundle($bundle);
            $bundle->save();
          }
        }
      }
      else if ($package['machine_name'] != $package['machine_name_short']) {
        $bundle_name = $this->getPrefix($package['machine_name'], $package['machine_name_short']);
        $bundle = $this->assigner->getBundle($bundle_name);
      }
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
