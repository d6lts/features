<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerAssigner.
 */

namespace Drupal\config_packager;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\config_packager\ConfigPackagerManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Site\Settings;

/**
 * Class responsible for performing package assignment.
 */
class ConfigPackagerAssigner implements ConfigPackagerAssignerInterface {

  /**
   * The package assignment method plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $assignerManager;

  /**
   * The configuration packager manager.
   *
   * @var \Drupal\config_packager\ConfigPackagerManagerInterface
   */
  protected $configPackagerManager;

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
   * Constructs a new ConfigPackagerAssigner object.
   *
   * @param \Drupal\config_packager\ConfigPackagerManagerInterface $config_packager_manager
   *    The configuration packager manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $assigner_manager
   *   The package assignment methods plugin manager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The configuration factory.
   */
  public function __construct(ConfigPackagerManagerInterface $config_packager_manager, PluginManagerInterface $assigner_manager, ConfigFactoryInterface $config_factory, StorageInterface $config_storage) {
    $this->configPackagerManager = $config_packager_manager;
    $this->assignerManager = $assigner_manager;
    $this->configFactory = $config_factory;
    $this->configStorage = $config_storage;
  }

  /**
   * Initializes the injected configuration packager manager with the assigner.
   *
   * This should be called right after instantiating the assigner to make it
   * available to the configuration packager manager without introducing a circular
   * dependency.
   */
  public function initConfigPackagerManager() {
    $this->configPackagerManager->setAssigner($this);
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->methods = array();
    $this->configPackagerManager->reset();
  }

  /**
   * Gets enabled assignment methods.
   *
   * @return array
   *   An array of enabled assignment methods.
   */
  protected function getEnabledAssigners() {
    return $this->configFactory->get('config_packager.settings')->get('assignment.enabled') ?: array();
  }

  /**
   * Performs package assignment using the specified assignment method.
   *
   * @param string $method_id
   *   The string identifier of the package assignment method to use to package
   *   configuration.
   */
  public function assignConfigPackages() {
    foreach ($this->getEnabledAssigners() as $method_id => $info) {
      $this->applyAssignmentMethod($method_id);
    }
  }

  /**
   * Apply a given package assignment method.
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
   * @return \Drupal\config_packager\ConfigPackagerAssignmentMethodInterface
   */
  protected function getAssignmentMethodInstance($method_id) {
    if (!isset($this->methods[$method_id])) {
      $instance = $this->assignerManager->createInstance($method_id, array());
      $instance->setConfigPackagerManager($this->configPackagerManager);
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

    // Order the package assignment method list by weight.
    asort($enabled_methods);
    foreach ($enabled_methods as $method_id => $weight) {
      if (!isset($definitions[$method_id])) {
        unset($enabled_methods[$method_id]);
      }
    }
    $this->configFactory->get('config_packager.settings')->set('assignment.enabled', $enabled_methods)->save();
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
    $this->configPackagerManager->reset();
    $this->saveConfiguration($this->getEnabledAssigners());
  }

}
