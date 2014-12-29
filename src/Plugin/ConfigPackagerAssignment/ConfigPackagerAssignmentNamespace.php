<?php

/**
 * @file
 * Contains \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentNamespace.
 */

namespace Drupal\config_packager\Plugin\ConfigPackagerAssignment;

use Drupal\config_packager\ConfigPackagerAssignmentMethodBase;

/**
 * Class for assigning configuration to packages based on namespaces.
 *
 * @Plugin(
 *   id = \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentNamespace::METHOD_ID,
 *   weight = 5,
 *   name = @Translation("Namespace"),
 *   description = @Translation("Add to packages configuration with a machine name containing that package's machine name."),
 * )
 */
class ConfigPackagerAssignmentNamespace extends ConfigPackagerAssignmentMethodBase {

  /**
   * The package assignment method id.
   */
  const METHOD_ID = 'namespace';

  /**
   * {@inheritdoc}
   */
  public function assignPackages() {
    $packages = array_keys($this->configPackagerManager->getPackages());
    $this->configPackagerManager->assignConfigByPattern(array_combine($packages, $packages));
  }

}
