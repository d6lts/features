<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesAssignmentMethodInterface.
 */

namespace Drupal\features;

use Drupal\features\FeaturesManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Interface for package assignment classes.
 */
interface FeaturesAssignmentMethodInterface {

  /**
   * Injects the features manager.
   *
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *   The features manager to be used to retrieve the configuration
   *   list and the already assigned packages.
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
