<?php

/**
 * @file
 * Contains \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentBaseType.
 */

namespace Drupal\config_packager\Plugin\ConfigPackagerAssignment;

use Drupal\component\Utility\Unicode;
use Drupal\config_packager\ConfigPackagerAssignmentMethodBase;

/**
 * Class for assigning configuration to packages based on entity types .
 *
 * @Plugin(
 *   id = \Drupal\config_packager\Plugin\ConfigPackagerAssignment\ConfigPackagerAssignmentBaseType::METHOD_ID,
 *   weight = -2,
 *   name = @Translation("Base type"),
 *   description = @Translation("Designated types of configuration are used as the base for configuration package modules. For example, if content types are selected as a base type, a package will be generated for each content type, including all configuration dependent on that content type."),
 *   config_route_name = "config_packager.assignment_base"
 * )
 */
class ConfigPackagerAssignmentBaseType extends ConfigPackagerAssignmentMethodBase {

  /**
   * The package assignment method id.
   */
  const METHOD_ID = 'base-type';

  /**
   * {@inheritdoc}
   */
  public function assignPackages() {
    $config_types = $this->configPackagerManager->getConfigTypes();
    $base_types = $this->configFactory->get('config_packager.assignment')->get('base.types');
    $config_collection = $this->configPackagerManager->getConfigCollection();

    foreach ($config_collection as $item_name => $item) {
      if (in_array($item['type'], $base_types)) {
        if (!isset($packages[$item['short_name']])) {
          $description = $this->t('Provide @label @type and related configuration.', array('@label' => $item['label'], '@type' => Unicode::strtolower($config_types[$item['type']])));
          if (isset($item['data']['description'])) {
            $description .= ' ' . $item['data']['description'];
          }
          $this->configPackagerManager->initPackage($item['short_name'], $item['label'], $description);
          $this->configPackagerManager->assignConfigPackage($item['short_name'], [$item_name]);
          $this->configPackagerManager->assignConfigDependents([$item_name]);
        }
      }
    }
  }

}
