<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesGenerationMethodInterface.
 */

namespace Drupal\features;

use Drupal\features\FeaturesManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for package assignment classes.
 */
interface FeaturesGenerationMethodInterface {

  /**
   * Injects the features manager.
   *
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *   The features manager to be used to retrieve the configuration
   *   list and the assigned packages.
   */
  public function setFeaturesManager(FeaturesManagerInterface $features_manager);

  /**
   * Injects the configuration factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory);
  
  /**
   * Prepares packages for generation.
   *
   * @param boolean $add_profile
   *   Whether to add an install profile. Defaults to FALSE.
   * @param array $profile
   *   Array of profile data.
   * @param array $packages
   *   Array of package data.
   *
   * @return array
   *   An array of packages data.
   */
  public function prepare($add_profile = FALSE, array &$profile = array(), array &$packages = array());

  /**
   * Performs package generation.
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
   * Responds to the submission of
   * \Drupal\features\Form\FeaturesExportForm.
   */
  public function exportFormSubmit(array &$form, FormStateInterface $form_state);

}
