<?php

/**
 * @file
 * Contains \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentDependency.
 */

namespace Drupal\config_packager\Plugin\ConfigPackagerAssignment;

use Drupal\config_packager\ConfigPackagerAssignmentMethodBase;

/**
 * Class for assigning configuration to packages based on configuration
 * dependencies.
 *
 * @Plugin(
 *   id = \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentDependency::METHOD_ID,
 *   weight = 10,
 *   name = @Translation("Dependency"),
 *   description = @Translation("Add to packages configuration dependent on items already in that package."),
 * )
 */
class ConfigPackagerAssignmentDependency extends ConfigPackagerAssignmentMethodBase {

  /**
   * The package assignment method id.
   */
  const METHOD_ID = 'dependency';

  /**
   * {@inheritdoc}
   */
  public function assignPackages() {
    $this->configPackagerManager->assignConfigDependents();
  }

}
