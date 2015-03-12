<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerAssignmentMethodBase.
 */

namespace Drupal\config_packager;

use Drupal\config_packager\ConfigPackagerManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for package assignment methods.
 */
abstract class ConfigPackagerAssignmentMethodBase implements ConfigPackagerAssignmentMethodInterface {
  use StringTranslationTrait;

  /**
   * The config packager manager.
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
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * {@inheritdoc}
   */
  public function setconfigPackagerManager(ConfigPackagerManagerInterface $config_packager_manager) {
    $this->configPackagerManager = $config_packager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigStorage(StorageInterface $config_storage) {
    $this->configStorage = $config_storage;
  }

}
