<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesGenerationMethodBase.
 */

namespace Drupal\features;

use Drupal\Component\Serialization\Yaml;
use Drupal\features\FeaturesManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for package assignment methods.
 */
abstract class FeaturesGenerationMethodBase implements FeaturesGenerationMethodInterface {
  use StringTranslationTrait;

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
   * {@inheritdoc}
   */
  public function setFeaturesManager(FeaturesManagerInterface $features_manager) {
    $this->featuresManager = $features_manager;
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
    return Yaml::encode($this->featuresManager->arrayMergeUnique($existing_info, $package_info));
  }

  /**
   * {@inheritdoc}
   */
  public function prepare($add_profile = FALSE, array &$profile = array(), array &$packages = array()) {
    // If no packages were specified, get all packages.
    if (empty($packages)) {
      $packages = $this->featuresManager->getPackages();
    }

    // If any packages exist, read in their files.
    $machine_names = $this->featuresManager->listPackageMachineNames(array_keys($packages));
    $existing_packages = $this->featuresManager->listPackageDirectories($machine_names, $add_profile);

    // Packages are keyed by short machine names while the existing packages
    // array is keyed by full machine names.
    if (empty($profile)) {
      $profile = $this->featuresManager->getProfile();
    }
    foreach ($packages as &$package) {
      list($full_name, $path) = $this->featuresManager->getExportInfo($package, $add_profile, $profile);

      // prepend the namespace of the current profile
      if (!empty($profile['machine_name'])) {
        $package['machine_name'] = $full_name;
        if (empty($existing_packages[$full_name])) {
          $package['files']['info']['filename'] = $profile['machine_name'] . '_' . $package['files']['info']['filename'];
        }
      }
      $package['directory'] = $path . '/' . $full_name;

      $this->preparePackage($add_profile, $package, $existing_packages);
    }
    // Clean up the $package pass by reference
    unset($package);

    if ($add_profile) {
      $profile['directory'] = 'profiles/' . $profile['directory'];
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
