<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesManagerInterface.
 */

namespace Drupal\features;

use Drupal\features\FeaturesAssignerInterface;
use Drupal\features\FeaturesGeneratorInterface;
use Drupal\Core\Extension\Extension;

/**
 * Provides an interface for the FeaturesManager.
 */
interface FeaturesManagerInterface {

  /**
   * Simple configuration.
   *
   * Core uses system.simple, but since we're using this key in configuration
   * arrays we can't include a period.
   * @see https://www.drupal.org/node/2297311
   */
  const SYSTEM_SIMPLE_CONFIG = 'system_simple';

  /**
   * Constants for package/module status
   */
  const STATUS_NO_EXPORT = 0;
  const STATUS_DISABLED = 1;
  const STATUS_ENABLED = 2;
  const STATUS_DEFAULT = STATUS_NO_EXPORT;

  /**
   * Constants for package/module state
   */
  const STATE_DEFAULT = 0;
  const STATE_OVERRIDDEN = 1;

  /**
   * Resets packages and configuration assignment.
   */
  public function reset();

  /**
   * Get an array of site configuration.
   * $param bool $reset
   *   If True, recalculate the configuration (undo all assignment methods)
   *
   * @return array
   *   An array of items, each with the following keys:
   *   - 'name': prefixed configuration item name.
   *   - 'name_short': configuration item name without prefix.
   *   - 'label': human readable name of configuration item.
   *   - 'type': type of configuration.
   *   - 'data': the contents of the configuration item in exported format.
   *   - 'dependents': array of names of dependent configuration items.
   */
  public function getConfigCollection($reset = FALSE);

  /**
   * Apply a namespace to the current config.
   * Causes Assignment plugins to be executed based on the new namespace
   * @param string $namespace
   *   if NULL, use the current namespace
   *   if empty, clears the namespace
   */
  public function applyNamespace($namespace = NULL);

  /**
   * Sets an array of site configuration.
   *
   * @param array $config_collection
   *   An array of items, each with the following keys:
   *   - 'name': prefixed configuration item name.
   *   - 'name_short': configuration item name without prefix.
   *   - 'label': human readable name of configuration item.
   *   - 'type': type of configuration.
   *   - 'data': the contents of the configuration item in exported format.
   *   - 'dependents': array of names of dependent configuration items.

   */
  public function setConfigCollection(array $config_collection);

  /**
   * Gets an array of packages.
   *
   * @return array
   *   An array of items, each with the following keys:
   *   - 'machine_name': machine name of the package such as 'example_article'.
   *   - 'machine_name_short': short machine name of the package such as
   *     'article'.
   *   - 'name': human readable name of the package such as 'Example Article'.
   *   - 'name_short': short human readable name of the package such as
   *     'Article'.
   *   - 'description': description of the package.
   *   - 'type': type of Drupal project ('module').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'themes': array of names of themes to enable.
   *   - 'config': array of names of configuration items.
   *   - 'directory': the extension's directory.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'subdirectory': any subdirectory of the file within the extension
   *         directory.
   *      - 'string': the contents of the file.
   */
  public function getPackages();

  /**
   * Sets an array of packages.
   *
   * @param array $packages
   *   An array of packages, each with the following keys:
   *   - 'machine_name': machine name of the package such as 'example_article'.
   *   - 'machine_name_short': short machine name of the package such as
   *     'article'.
   *   - 'name': human readable name of the package such as 'Example Article'.
   *   - 'name_short': short human readable name of the package such as
   *     'Article'.
   *   - 'description': description of the package.
   *   - 'type': type of Drupal project ('module').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'themes': array of names of themes to enable.
   *   - 'config': array of names of configuration items.
   *   - 'directory': the extension's directory.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'subdirectory': any subdirectory of the file within the extension
   *         directory.
   *      - 'string': the contents of the file.
   */
  public function setPackages(array $packages);

  /**
   * Gets a representation of an install profile.
   *
   * @return array
   *   An array with the following keys:
   *   - 'machine_name': machine name of the profile such as 'example'.
   *   - 'machine_name_short': short machine name. For a profile, this is the
   *     same as the machine_name.
   *   - 'name': human readable name of the package such as 'Example'.
   *   - 'name_short': short human readable name. For a profile, this is the
   *     same as the name.
   *   - 'description': description of the profile.
   *   - 'type': type of Drupal project ('profile').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'themes': array of names of themes to enable.
   *   - 'config': array of names of configuration items.
   *   - 'directory': the extension's directory.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'subdirectory': any subdirectory of the file within the extension
   *         directory.
   *      - 'string': the contents of the file.
   */
  public function getProfile();

  /**
   * Sets a representation of an install profile.
   *
   * @param array $profile
   *   An array with the following keys:
   *   - 'machine_name': machine name of the profile such as 'example'.
   *   - 'machine_name_short': short machine name. For a profile, this is the
   *     same as the machine_name.
   *   - 'name': human readable name of the package such as 'Example'.
   *   - 'name_short': short human readable name. For a profile, this is the
   *     same as the name.
   *   - 'description': description of the profile.
   *   - 'type': type of Drupal project ('profile').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'themes': array of names of themes to enable.
   *   - 'config': array of names of configuration items.
   *   - 'directory': the extension's directory.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'subdirectory': any subdirectory of the file within the extension
   *         directory.
   *      - 'string': the contents of the file.
   */
  public function setProfile(array $profile);

  /**
   * Gets a reference to a package assigner.
   *
   * @return \Drupal\features\FeaturesAssignerInterface
   *   The package assigner.
   */
  public function getAssigner();

  /**
   * Injects the package assigner.
   *
   * @param \Drupal\features\FeaturesAssignerInterface @assigner
   *   The package assigner.
   */
  public function setAssigner(FeaturesAssignerInterface $assigner);

  /**
   * Gets a reference to a package generator.
   *
   * @return \Drupal\features\FeaturesGeneratorInterface
   *   The package generator.
   */
  public function getGenerator();

  /**
   * Injects the package generator.
   *
   * @param \Drupal\features\FeaturesGeneratorInterface @generator
   *   The package generator.
   */
  public function setGenerator(FeaturesGeneratorInterface $generator);

  /**
   * Return the current export settings
   * @return array
   *   'folder' - subdirectory to export packages to
   *   'namespace' - module namespace being being exported
   */
  public function getExportSettings();

  /**
   * Return the current assignment settings
   */
  public function getAssignmentSettings();

  /**
   * Initializes a configuration package.
   *
   * @param string $machine_name
   *   Machine name of the package.
   * @param string $name
   *   Human readable name of the package.
   * @param string $description
   *   Description of the package.
   * @return array
   *   Returns the created package array
   */
  public function initPackage($machine_name, $name = NULL, $description = '');

  /**
   * List modules that are existing exported Packages
   * @param bool $enabled
   *   Determine if only enabled modules are searched
   * @param string $namespace
   *   If set, only returns modules matching the namespace
   * @return array
   *   Module's info.yml config data
   */
  public function getExistingPackages($enabled = FALSE, $namespace = NULL);

  /**
   * Lists directories in which packages are present.
   *
   * This method scans to find package modules whether or not they are
   * currently active (installed). As well as the directories that are
   * usually scanned for modules and profiles, a profile directory for the
   * current profile is scanned if it exists. For example, if the value
   * for FeaturesManager::profile['machine_name'] is 'example', a
   * directory profiles/example will be scanned if it exists. Therefore, when
   * regenerating package modules, existing ones from a prior export will be
   * recognized.
   *
   * @param string[] $machine_names
   *   Package machine names.
   * @param boolean $add_profile
   *   Whether to add an install profile. Defaults to FALSE.
   *
   * @return array
   *   Array of package directories keyed by package machine name.
   */
  public function listPackageDirectories(array $machine_names = array(), $add_profile = FALSE);

  /**
   * Initializes a "core" configuration package.
   */
  public function initCorePackage();

  /**
   * Assigns a set of configuration items to a given package or profile.
   *
   * @param string $machine_name
   *   Machine name of a package or the profile.
   * @param string[] $item_names
   *   Configuration item names.
   *
   * @throws Exception
   */
  public function assignConfigPackage($package_name, array $item_names);

  /**
   * Assigns configuration items with names matching given strings to given
   * packages.
   *
   * @param array $patterns
   *   Array with string patterns as keys and package machine names as values.
   */
  public function assignConfigByPattern(array $patterns);

  /**
   * For given configuration items, assigns any dependent configuration to the
   * same package.
   *
   * @param string[] $item_names
   *   Configuration item names.
   */
  public function assignConfigDependents(array $item_names = NULL);

  /**
   * Merges two arrays and processes the resulting array, ensuring values are
   * unique and sorted.
   *
   * @param array $array1
   *   The first array.
   * @param array $array2
   *   The second array.
   * @param string[] $keys
   *   Keys to merge. If not specified, all keys present will be merged.
   *
   * @return array
   *   An array with the merged and processed results.
   */
  public function arrayMergeUnique(array $array1, array $array2, $keys = array());

  /**
   * Lists package machine names.
   *
   * @param string[] $machine_names_short
   *   Machine names. If empty, all availble package short names will be
   *   returned.
   * @param boolean $add_profile
   *   Whether to add an install profile. Defaults to FALSE.
   *
   * @return array
   *   Array of short names.
   */
  public function listPackageMachineNames(array $machine_names_short = array(), $add_profile = FALSE);

  /**
   * Lists short package names.
   *
   * The FeaturesManager::packages property is keyed by short package
   * names while each package has a 'machine_name' key that is the short name
   * prefixed by the profile machine name and an underscore. Here we remove
   * this prefix and return short names.
   *
   * @param string[] $machine_names
   *   Machine names. If empty, all availble package short names will be
   *   returned.
   *
   * @return array
   *   Array of short names.
   */
  public function listPackageMachineNamesShort(array $machine_names = array());

  /**
   * Lists the types of configuration available on the site.
   *
   * @return array
   *   An array with machine name keys and human readable values.
   */
  public function listConfigTypes();

  /**
   * Lists stored configuration for a given configuration type.
   *
   * @param string $config_type
   *   The type of configuration.
   */
  public function listConfigByType($config_type);

  /**
   * Return an array of installed modules.
   *
   * If a $name and/or $namespace is specified, only matching modules will be
   * returned. Otherwise, all install are returned.
   *
   * @param string[] $names
   *   Names of specific modules to return.
   * @param string $namespace
   *   A namespace prefix to match modules by.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   An associative array whose keys are the names of the modules and whose
   *   values are Extension objects.
   *
   * @see Drupal\Core\Extension\ModuleHandlerInterface::getModuleList()
   */
  public function getModuleList(array $names = array(), $namespace = NULL);

  /**
   * Lists names of configuration objects provided by a given extension.
   *
   * If a $name and/or $namespace is specified, only matching modules will be
   * returned. Otherwise, all install are returned.
   *
   * @param Extension $extension
   *   An Extension object.
   *
   * @return array
   *   An array of configuration object names.
   */
  public function listExtensionConfig(Extension $extension);

  /**
   * Iterates through packages and profile and prepares file names and
   * contents.
   *
   * @param boolean $add_profile
   *   Whether to add an install profile. Defaults to FALSE.
   */
  public function prepareFiles($add_profile = FALSE);

  /**
   * Returns the full name of a config item.
   *
   * @param string $type
   *   The config type, or '' to indicate $name is already prefixed.
   * @param string $name
   *   The config name, without prefix.
   *
   * @return string
   *   The config item's full name.
   */
  public function getFullName($type, $name);

  /**
   * Return the full machine name and directory for exporting a package
   * @param string $package
   * @param bool $add_profile
   * @param array $profile
   * @return array($full_name, $directory)
   */
  public function getExportInfo($package, $add_profile = FALSE, $profile = NULL);


  /**
   * Helper function to return a translatable label for the different status constants
   * @param int $status
   * @return string
   */
  public function statusLabel($status);

  /**
   * Helper function to return a translatable label for the different state constants
   * @param int $state
   * @return string
   */
  public function stateLabel($state);

}
