<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerAssignerInterface
 */

namespace Drupal\config_packager;

/**
 * Common interface for configuration packager assignment services.
 *
 * The configuration packaging API is based on two major concepts:
 * - Packages: modules into which configuration is packaged.
 * - Package assignment methods: responsible for `determining
 *   which package to assign a given piece of configuration to.
 * Assignment methods are customizable.
 *
 * Configuration Packager defines several package assignment methods, which are
 * simple plugin classes that implement a particular logic to assign pieces of
 * configuration to a given package (module).
 *
 * Modules can define additional package assignment methods by simply providing
 * the related plugins, and alter existing methods through
 * hook_config_packager_assignment_method_info_alter(). Here is an example
 * snippet:
 * @code
 * function mymodule_config_packager_assignment_method_info_alter(&$assignment_info) {
 *   // Replace the original plugin with our own implementation.
 *   $method_id = \Drupal\config_packager\Plugin\ConfigPackagerAssignment\PackageAssignmentBaseType::METHOD_ID;
 *   $assignment_info[$method_id]['class'] = 'Drupal\my_module\Plugin\ConfigPackagerAssignment\MyPackageAssignmentBaseType';
 * }
 *
 * class MyPackageAssignmentBaseType extends PackageAssignmentBaseType {
 *   public function assignPackages() {
 *     // Insert customization here.
 *   }
 * }
 * ?>
 * @endcode
 *
 * For more information, see
 * @link http://drupal.org/node/2404473 Developing for Configuration Packager @endlink
 */
interface ConfigPackagerAssignerInterface {

  /**
   * The package assignment method id for the package assigner itself.
   */
  const METHOD_ID = 'assigner-default';

  /**
   * Resets the assigned packages and the method instances.
   */
  public function reset();

  /**
   * Apply all enabled package assignment methods.
   */
  public function assignConfigPackages();

  /**
   * Returns the enabled package assignment methods.
   *
   * @return array
   *   An array of package assignment method definitions keyed by method id.
   */
  public function getAssignmentMethods();

  /**
   * Saves a list of package assignment methods.
   *
   * @param array $enabled_methods
   *   An array of package assignment method weights keyed by method ID.
   */
  public function saveConfiguration($enabled_methods);

  /**
   * Resave the configuration to purge missing assignment methods.
   */
  public function purgeConfiguration();

}
