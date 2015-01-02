<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerGenerationMethodBase.
 */

namespace Drupal\config_packager;

use Drupal\Component\Serialization\Yaml;
use Drupal\config_packager\ConfigPackagerManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for package assignment methods.
 */
abstract class ConfigPackagerGenerationMethodBase implements ConfigPackagerGenerationMethodInterface {
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
   * {@inheritdoc}
   */
  public function setConfigPackagerManager(ConfigPackagerManagerInterface $config_packager_manager) {
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
  public function exportFormSubmit(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Merge an info file into a package's info file.
   *
   * @param array $package_info
   *   The Yaml encoded package info.
   * @param $info_file_uri
   *   The info file's URI.
   */
  protected function mergeInfoFile($package_info, $info_file_uri) {
    $package_info = Yaml::decode($package_info);
    $existing_info = \Drupal::service('info_parser')->parse($info_file_uri);
    return Yaml::encode($this->configPackagerManager->arrayMergeUnique($package_info, $existing_info));
  }

}
