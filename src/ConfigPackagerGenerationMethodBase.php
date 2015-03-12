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
   * Merges an info file into a package's info file.
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

  /**
   * {@inheritdoc}
   */
  public function prepare($add_profile = FALSE, array &$profile = array(), array &$packages = array()) {
    // If no packages were specified, get all packages.
    if (empty($packages)) {
      $packages = $this->configPackagerManager->getPackages();
    }

    // If any packages exist, read in their files.
    $machine_names = $this->configPackagerManager->listPackageMachineNames(array_keys($packages));
    $existing_packages = $this->configPackagerManager->listPackageDirectories($machine_names, $add_profile);

    // Packages are keyed by short machine names while the existing packages
    // array is keyed by full machine names.
    foreach ($packages as &$package) {
      $this->preparePackage($add_profile, $package, $existing_packages);
    }
    // Clean up the $package pass by reference
    unset($package);

    if ($add_profile) {
      if (empty($profile)) {
        $profile = $this->configPackagerManager->getProfile();
      }
      $this->preparePackage($add_profile, $profile, $existing_packages);
    }
  }

  /**
   * Performs any required changes on a package or profile prior to generation.
   *
   * @param boolean $add_profile
   *   Whether to add an install profile. Defaults to FALSE.
   * @param array $package
   *   The package to be prepared.
   * @param $existing_packages
   *   An array of existing packages with machine names as keys and paths as
   *   values.
   */
  abstract protected function preparePackage($add_profile, &$package, $existing_packages);

}
