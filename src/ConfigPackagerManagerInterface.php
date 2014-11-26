<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerManagerInterface.
 */

namespace Drupal\config_packager;

/**
 * Provides an interface for the ConfigPackagerManager.
 */
interface ConfigPackagerManagerInterface {

  /**
   * Simple configuration.
   */
  const SYSTEM_SIMPLE_CONFIG = 'system.simple';

  /**
   * Reset packages and configuration assignment.
   */
  public function reset();

  /**
   * Initialize a configuration package.
   */
  public function initPackage($machine_name, $name = NULL, $description = '');

  /**
   * Populates ConfigPackagerManager::packages array.
   */
  public function setPackages(array $packages);

  /**
   * Generates a packager tarball.
   */
  public function generate();

}
