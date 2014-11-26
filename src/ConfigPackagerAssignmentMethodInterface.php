<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerAssignmentMethodInterface.
 */

namespace Drupal\config_packager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\config_packager\ConfigPackagerManagerInterface;

/**
 * Interface for package assignment classes.
 */
interface ConfigPackagerAssignmentMethodInterface {

  /**
   * Injects the config packager manager.
   *
   * @param \Drupal\config_packager\ConfigPackagerManagerInterface $config_packager_manager
   *   The congig packager manager to be used to retrieve the configuration
   *   list and the already assigned packages.
   */
  public function setConfigPackagerManager(ConfigPackagerManagerInterface $config_packager_manager);

  /**
   * Injects the configuration factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory);

  /**
   * Performs package assignment.
   */
  public function assignPackages();

}
