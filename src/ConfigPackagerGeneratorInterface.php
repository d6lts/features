<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerGeneratorInterface
 */

namespace Drupal\config_packager;

use Drupal\Core\Form\FormStateInterface;

/**
 * Common interface for configuration packager generation services.
 *
 * The configuration packaging API is based on two major concepts:
 * - Packages: modules into which configuration is packaged.
 * - Package generation methods: responsible for `determining
 *   which package to assign a given piece of configuration to.
 * Generation methods are customizable.
 *
 * Configuration Packager defines several package generation methods, which are
 * simple plugin classes that implement a particular logic to assign pieces of
 * configuration to a given package (module).
 *
 * Modules can define additional package generation methods by simply providing
 * the related plugins, and alter existing methods through
 * hook_config_packager_generation_method_info_alter(). Here is an example
 * snippet:
 * @code
 * function mymodule_config_packager_generation_method_info_alter(&$generation_info) {
 *   // Replace the original plugin with our own implementation.
 *   $method_id = \Drupal\config_packager\Plugin\ConfigPackagerGeneration\PackageGenerationArchive::METHOD_ID;
 *   $generation_info[$method_id]['class'] = 'Drupal\my_module\Plugin\ConfigPackagerGeneration\MyPackageGenerationArchive';
 * }
 *
 * class MyPackageGenerationArchive extends PackageGenerationArchive {
 *   public function assignPackages() {
 *     // Insert customization here.
 *   }
 * }
 * ?>
 * @endcode
 *
 * For more information, see
 * @link http://drupal.org/node/1497272 Language Negotiation API @endlink
 */
interface ConfigPackagerGeneratorInterface {

  /**
   * The package generation method id for the package generator itself.
   */
  const METHOD_ID = 'generator-default';

  /**
   * Resets the assigned packages and the method instances.
   */
  public function reset();

  /**
   * Apply a given package generation method.
   *
   *
   * @param string $method_id
   *   The string identifier of the package generation method to use to package
   *   configuration.
   * @param boolean $add_profile
   *   Whether to add an install profile. Defaults to FALSE.
   * @param array $packages
   *   Array of package data.
   *
   * @return array
   *   Array of results for profile and/or packages, each result including the
   *   following keys:
   *   - 'success': boolean TRUE or FALSE for successful writing.
   *   - 'display': boolean TRUE if the message should be displayed to the
   *     user, otherwise FALSE.
   *   - 'message': a message about the result of the operation.
   *   - 'variables': an array of substitutions to be used in the message.
   */
  public function applyGenerationMethod($method_id, $add_profile = FALSE, array $packages = array());

  /**
   * Respond to the submission of
   * \Drupal\config_packager\Form\ConfigPackagerExportForm.
   */
  public function applyExportFormSubmit($method_id, &$form, FormStateInterface $form_state);

  /**
   * Returns the enabled package generation methods.
   *
   * @return array
   *   An array of package generation method definitions keyed by method id.
   */
  public function getGenerationMethods();

  /**
   * Generate file representations of configuration packages.
   *
   * @param string $method_id
   *   The ID of the generation method to use.
   * @param array $package_names
   *   Array of names of packages to be generated. If none are specified, all
   *   available packages will be added.
   * @param boolean $short_names
   *   Boolean TRUE is any package names given in the $package_names argument
   *   are in the short machine name format, FALSE if they are not.
   */
  public function generatePackages($method_id, array $package_names = array(), $short_names = TRUE);

  /**
   * Generate file representations of an install profile and configuration
   * packages.
   *
   * @param string $method_id
   *   The ID of the generation method to use.
   * @param array $package_names
   *   Array of names of packages to be generated. If none are specified, all
   *   available packages will be added.
   * @param boolean $short_names
   *   Boolean TRUE is any package names given in the $package_names argument
   *   are in the short machine name format, FALSE if they are not.
   */
  public function generateProfile($method_id, array $package_names = array(), $short_names = FALSE);

}
