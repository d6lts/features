<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesAssigner.
 */

namespace Drupal\features;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\features\FeaturesManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Class responsible for performing package assignment.
 */
class FeaturesAssigner implements FeaturesAssignerInterface {

  /**
   * The package assignment method plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $assignerManager;

  /**
   * The features manager.
   *
   * @var \Drupal\features\FeaturesManagerInterface
   */
  protected $featuresManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Local cache for package assignment method instances.
   *
   * @var array
   */
  protected $methods;

  /**
   * Constructs a new FeaturesAssigner object.
   *
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *    The features manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $assigner_manager
   *   The package assignment methods plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The configuration factory.
   */
  public function __construct(FeaturesManagerInterface $features_manager, PluginManagerInterface $assigner_manager, ConfigFactoryInterface $config_factory, StorageInterface $config_storage) {
    $this->featuresManager = $features_manager;
    $this->assignerManager = $assigner_manager;
    $this->configFactory = $config_factory;
    $this->configStorage = $config_storage;
  }

  /**
   * Initializes the injected features manager with the assigner.
   *
   * This should be called right after instantiating the assigner to make it
   * available to the features manager without introducing a circular
   * dependency.
   */
  public function initFeaturesManager() {
    $this->featuresManager->setAssigner($this);
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->methods = array();
    $this->featuresManager->reset();
  }

  /**
   * Gets enabled assignment methods.
   *
   * @return array
   *   An array of enabled assignment methods, sorted by weight.
   */
  public function getEnabledAssigners() {
    $enabled = $this->configFactory->get('features.settings')->get('assignment.enabled');
    $weights = $this->configFactory->get('features.settings')->get('assignment.method_weights');
    foreach ($enabled as $key => $value) {
      $enabled[$key] = $weights[$key];
    }
    asort($enabled);
    return $enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function assignConfigPackages() {
    foreach ($this->getEnabledAssigners() as $method_id => $info) {
      $this->applyAssignmentMethod($method_id);
    }
  }

  /**
   * Applies a given package assignment method.
   *
   * @param string $method_id
   *   The string identifier of the package assignment method to use to package
   *   configuration.
   */
  protected function applyAssignmentMethod($method_id) {
    $this->getAssignmentMethodInstance($method_id)->assignPackages();
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignmentMethods() {
    return $this->assignerManager->getDefinitions();
  }

  /**
   * Returns an instance of the specified package assignment method.
   *
   * @param string $method_id
   *   The string identifier of the package assignment method to use to package
   *   configuration.
   *
   * @return \Drupal\features\FeaturesAssignmentMethodInterface
   */
  protected function getAssignmentMethodInstance($method_id) {
    if (!isset($this->methods[$method_id])) {
      $instance = $this->assignerManager->createInstance($method_id, array());
      $instance->setFeaturesManager($this->featuresManager);
      $instance->setConfigFactory($this->configFactory);
      $instance->setConfigStorage($this->configStorage);
      $this->methods[$method_id] = $instance;
    }
    return $this->methods[$method_id];
  }

  /**
   * {@inheritdoc}
   */
  public function saveConfiguration($enabled_methods) {
    $definitions = $this->getAssignmentMethods();

    foreach ($enabled_methods as $method_id) {
      if (!isset($definitions[$method_id])) {
        unset($enabled_methods[$method_id]);
      }
    }
    $this->configFactory->getEditable('features.settings')->set('assignment.enabled', $enabled_methods)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function purgeConfiguration() {
    // Ensure that we are getting the defined package assignment information.
    // An invocation of \Drupal\Core\Extension\ModuleHandler::install() or
    // \Drupal\Core\Extension\ModuleHandler::uninstall() could invalidate the
    // cached information.
    $this->assignerManager->clearCachedDefinitions();
    $this->featuresManager->reset();
    $this->saveConfiguration($this->getEnabledAssigners());
  }

}
