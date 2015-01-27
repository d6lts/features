<?php

/**
 * @file
 * Contains \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentCoreType.
 */

namespace Drupal\config_packager\Plugin\ConfigPackagerAssignment;

use Drupal\config_packager\ConfigPackagerAssignmentMethodBase;

/**
 * Class for assigning configuration to a core package based on entity types.
 *
 * @Plugin(
 *   id = \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentCoreType::METHOD_ID,
 *   weight = 0,
 *   name = @Translation("Core type"),
 *   description = @Translation("Assign designated types of configuration to a core configuration package module. For example, if image styles are selected as a core type, a core package will be generated and image styles will be assigned to it."),
 *   config_route_name = "config_packager.assignment_core"
 * )
 */
class ConfigPackagerAssignmentCoreType extends ConfigPackagerAssignmentMethodBase {

  /**
   * The package assignment method id.
   */
  const METHOD_ID = 'core-type';

  /**
   * {@inheritdoc}
   */
  public function assignPackages() {
    $config_types = $this->configPackagerManager->getConfigTypes();
    $core_types = $this->configFactory->get('config_packager.assignment')->get('core.types');
    $config_collection = $this->configPackagerManager->getConfigCollection();

    $initialized = FALSE;
    foreach ($config_collection as $item_name => $item) {
      if (in_array($item['type'], $core_types)) {
        if (!$initialized) {
          $this->configPackagerManager->initCorePackage();
          $initialized = TRUE;
        }
        try {
          $this->configPackagerManager->assignConfigPackage('core', [$item_name]);
        }
        catch(\Exception $exception) {
          \Drupal::logger('config_packager')->error($exception->getMessage());
        }
      }
    }
  }

}
