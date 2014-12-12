<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerAssignmentMethodInterface.
 */

namespace Drupal\config_packager;

use Drupal\config_packager\ConfigPackagerManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;

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
   * Injects the configuration storage.
   *
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The configuration storage.
   */
  public function setConfigStorage(StorageInterface $config_storage);

  /**
   * Performs package assignment.
   */
  public function assignPackages();

}
