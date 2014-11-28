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
   *
   * Core uses system.simple, but since we're using this key in configuration
   * arrays we can't include a period.
   * @see https://www.drupal.org/node/2297311
   */
  const SYSTEM_SIMPLE_CONFIG = 'system_simple';

  /**
   * Reset packages and configuration assignment.
   */
  public function reset();

  /**
   * Get an array of site configuration.
   *
   * @return array
   *   An array of items, each with the following keys:
   *   - 'name': prefixed configuration item name.
   *   - 'short_name': configuration item name without prefix.
   *   - 'label': human readable name of configuration item.
   *   - 'type': type of configuration.
   *   - 'data': the contents of the configuration item in exported format.
   *   - 'dependents': array of names of dependent configuration items.
   */
  public function getConfigCollection();

  /**
   * Set an array of site configuration.
   *
   * @param array $config_collection
   *   An array of items, each with the following keys:
   *   - 'name': prefixed configuration item name.
   *   - 'short_name': configuration item name without prefix.
   *   - 'label': human readable name of configuration item.
   *   - 'type': type of configuration.
   *   - 'data': the contents of the configuration item in exported format.
   *   - 'dependents': array of names of dependent configuration items.

   */
  public function setConfigCollection(array $config_collection);

  /**
   * Get an array of packages.
   *
   * @return array
   *   An array of items, each with the following keys:
   *   - 'machine_name': maching name of the package.
   *   - 'name': human readable name of the package.
   *   - 'description': description of the package.
   *   - 'type': type of Drupal project ('module').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'config': array of names of configuration items.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'string': the contents of the file.
   */
  public function getPackages();

  /**
   * Set an array of packages.
   *
   * @param array $packages
   *   An array of packages, each with the following keys:
   *   - 'machine_name': machine name of the package.
   *   - 'name': human readable name of the package.
   *   - 'description': description of the package.
   *   - 'type': type of Drupal project ('module').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'config': array of names of configuration items.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'string': the contents of the file.
   */
  public function setPackages(array $packages);

  /**
   * Get a representation of an install profile.
   *
   * @return array
   *   An array with the following keys:
   *   - 'machine_name': machine name of the profile.
   *   - 'name': human readable name of the profile.
   *   - 'description': description of the profile.
   *   - 'type': type of Drupal project ('profile').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'config': array of names of configuration items.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'string': the contents of the file.
   */
  public function getProfile();

  /**
   * Get a representation of man install profile.
   *
   * @param array $profile
   *   An array with the following keys:
   *   - 'machine_name': machine name of the profile.
   *   - 'name': human readable name of the profile.
   *   - 'description': description of the profile.
   *   - 'type': type of Drupal project ('profile').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'config': array of names of configuration items.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'string': the contents of the file.
   */
  public function setProfile(array $profile);

  /**
   * Get a reference to a package assigner.
   *
   * @return \Drupal\config_packager\ConfigPackagerAssignerInterface
   *   The package assigner.
   */
  public function getAssigner();

  /**
   * Injects the package assigner.
   *
   * @param \Drupal\config_packager\ConfigPackagerAssignerInterface @assigner
   *   The package assigner.
   */
  public function setAssigner(ConfigPackagerAssignerInterface $assigner);

  /**
   * Initialize a configuration package.
   *
   * @param string $machine_name
   *   Machine name of the package.
   * @param string $name
   *   Human readable name of the package.
   * @param string $description
   *   Description of the package.
   */
  public function initPackage($machine_name, $name = NULL, $description = '');

  /**
   * Initialize a "core" configuration package.
   */
  public function initCorePackage();

  /**
   * Assign a set of configuration items to a given package.
   *
   * @param string $machine_name
   *   Machine name of the package.
   * @param array $item_names
   *   Array of configuration item names.
   */
  public function assignConfigPackage($package_name, array $item_names);

  /**
   * Assign configuration items with names matching given strings to given
   * packages.
   *
   * @param array $patterns
   *   Array with string patterns as keys and package machine names as values.
   */
  public function assignConfigByPattern(array $patterns);

  /**
   * For given configuration items, assign any dependent configuration to the
   * same package.
   *
   * @param array $item_names
   *   Array of configuration item names.
   */
  public function assignConfigDependents(array $item_names = NULL);

  /**
   * Get the types of configuration available on the site.
   *
   * @return array
   *   An array with machine name keys and human readable values.
   */
  public function getConfigTypes();

  /**
   * Generates a packager tarball.
   */
  public function generate();

}
