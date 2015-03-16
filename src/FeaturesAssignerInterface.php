<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesAssignerInterface
 */

namespace Drupal\features;

/**
 * Common interface for features assignment services.
 *
 * The configuration packaging API is based on two major concepts:
 * - Packages: modules into which configuration is packaged.
 * - Package assignment methods: responsible for `determining
 *   which package to assign a given piece of configuration to.
 * Assignment methods are customizable.
 *
 * Features defines several package assignment methods, which are
 * simple plugin classes that implement a particular logic to assign pieces of
 * configuration to a given package (module).
 *
 * Modules can define additional package assignment methods by simply providing
 * the related plugins, and alter existing methods through
 * hook_features_assignment_method_info_alter(). Here is an example
 * snippet:
 * @code
 * function mymodule_features_assignment_method_info_alter(&$assignment_info) {
 *   // Replace the original plugin with our own implementation.
 *   $method_id = \Drupal\features\Plugin\FeaturesAssignment\PackageAssignmentBaseType::METHOD_ID;
 *   $assignment_info[$method_id]['class'] = 'Drupal\my_module\Plugin\FeaturesAssignment\MyPackageAssignmentBaseType';
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
interface FeaturesAssignerInterface {

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
   *   An array of package assignment method IDs.
   */
  public function getAssignmentMethods();

  /**
   * Saves a list of package assignment methods.
   *
   * @param array $enabled_methods
   *   An array of package assignment method IDs.
   */
  public function saveConfiguration($enabled_methods);

  /**
   * Resave the configuration to purge missing assignment methods.
   */
  public function purgeConfiguration();

}
