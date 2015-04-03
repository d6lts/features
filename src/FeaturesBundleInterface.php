<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesBundleInterface.
 */

namespace Drupal\features;

use Drupal\features\FeaturesManagerInterface;
use Drupal\features\FeaturesAssignerInterface;
use Drupal\features\FeaturesGeneratorInterface;
use Drupal\Core\Extension\Extension;

/**
 * Provides an interface for the FeaturesBundle object.
 */
interface FeaturesBundleInterface {

  /**
   * Returns TRUE if this is the default bundle
   * @return bool
   */
  public function isDefault();

  /**
   * Get the machine name of a bundle.
   * @return string
   */
  public function getMachineName();

  /**
   * Set the machine name of a bundle.
   * @param string $machine_name
   */
  public function setMachineName($machine_name);

  /**
   * Get the human readable name of a bundle.
   * @return string
   */
  public function getName();

  /**
   * Set the human readable name of a bundle.
   * @param string $name
   */
  public function setName($name);

  /**
   * Return a full machine name prefixed with the bundle name
   * @param string $short_name short machine_name
   * @return string full machine_name
   */
  public function getFullName($short_name);

  /**
   * Determine if the $machine_name is prefixed by the bundle
   * @param string $machine_name
   * @return bool
   */
  public function inBundle($machine_name);

  /**
   * Get the description of a bundle.
   * @return string
   */
  public function getDescription();

  /**
   * Set the description of a bundle.
   * @param string $description
   */
  public function setDescription($description);

  /**
   * Get option for using a profile with this bundle.
   * @return bool
   */
  public function isProfile();

  /**
   * Set option for using a profile with this bundle.
   * @param bool $value
   */
  public function setIsProfile($value);

  /**
   * Return the machine name of the profile.
   * If the bundle doesn't use a profile, return the current site profile.
   * @return string
   */
  public function getProfileName();

  /**
   * Set the name of the profile associated with this bundle
   * @param string $machine_name
   */
  public function setProfileName($machine_name);

  /**
   * Get the list of enabled assignment methods.
   * @return array of method ids keyed by assignment method ids
   */
  public function getEnabledAssignments();

  /**
   * Set the list of enabled assignment methods.
   * @param array $assignments
   *   array of values keyed by assignment method ids.  Non-empty value is enabled.
   */
  public function setEnabledAssignments($assignments);

  /**
   * Get the weights of the assignment methods.
   * @return array keyed by assignment method_id with a numeric weight.
   */
  public function getAssignmentWeights();

  /**
   * Set the weights of the assignment methods.
   * @param array $assignments
   *   array keyed by assignment method_id with a numeric weight value.
   */
  public function setAssignmentWeights($assignments);

  /**
   * Get settings specific to an assignment method.
   * @param string $method_id
   *   if NULL, return all assignment settings keyed by method_id
   * @return array of settings.  Format specific to assignment method.
   */
  public function getAssignmentSettings($method_id);

  /**
   * Set settings specific to an assignment method.
   * @param string $method_id
   *   if NULL, all $settings are given keyed by method_id
   * @param array $settings
   */
  public function setAssignmentSettings($method_id, $settings);

  /**
   * Get global settings for a bundle.
   * @return array
   *   'folder': subfolder to export modules within this package set
   *   'profile': boolean to determine if set is a profile
   */
  public function getSettings();

  /**
   * Set the global settings for a bundle.
   * @param $settings see @getSettings.
   */
  public function setSettings($settings);

  /**
   * Load a named bundle from the active config.
   * @param string $machine_name
   *   if omitted, use the current machine_name, otherwise replace the data
   *   in the bundle with the data from the config.
   */
  public function load($machine_name = NULL);

  /**
   * Save the bundle to the active config.
   */
  public function save();

  /**
   * Remove the bundle from the active config
   */
  public function remove();

}
