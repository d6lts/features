<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerGenerationMethodInterface.
 */

namespace Drupal\config_packager;

use Drupal\config_packager\ConfigPackagerManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for package assignment classes.
 */
interface ConfigPackagerGenerationMethodInterface {

  /**
   * Inject the config packager manager.
   *
   * @param \Drupal\config_packager\ConfigPackagerManagerInterface $config_packager_manager
   *   The congig packager manager to be used to retrieve the configuration
   *   list and the assigned packages.
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
   * Prepare packages for generation.
   *
   * @param boolean $add_profile
   *   Whether to add an install profile. Defaults to FALSE.
   * @param array $packages
   *   Array of package data.
   *
   * @return array
   *   An array of packages data.
   */
  public function prepare($add_profile = FALSE, array $packages = array());

  /**
   * Perform package generation.
   *
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
  public function generate($add_profile = FALSE, array $packages = array());

  /**
   * Respond to the submission of
   * \Drupal\config_packager\Form\ConfigPackagerExportForm.
   */
  public function exportFormSubmit(array &$form, FormStateInterface $form_state);

}
