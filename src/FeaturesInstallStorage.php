<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesInstallStorage.
 */

namespace Drupal\features;

use Drupal\Core\Site\Settings;
use Drupal\Core\Config\ExtensionInstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ExtensionDiscovery;

/**
 * Storage to access configuration and schema in enabled extensions.
 * Overrides the normal ExtensionInstallStorage to prevent profile from overriding
 * Also supports modules that are not installed yet
 *
 * @see \Drupal\Core\Config\ExtensionInstallStorage
 */
class FeaturesInstallStorage extends ExtensionInstallStorage {

  /**
   * Overrides \Drupal\Core\Config\ExtensionInstallStorage::__construct().
   * Sets includeProfile to FALSE
   *
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The active configuration store where the list of enabled modules and
   *   themes is stored.
   * @param string $directory
   *   The directory to scan in each extension to scan for files. Defaults to
   *   'config/install'.
   * @param string $collection
   *   (optional) The collection to store configuration in. Defaults to the
   *   default collection.
   */
  public function __construct(StorageInterface $config_storage, $directory = self::CONFIG_INSTALL_DIRECTORY, $collection = StorageInterface::DEFAULT_COLLECTION) {
    parent::__construct($config_storage, $directory, $collection, FALSE);
  }

  /**
   * Return a list of modules regardless of if they are enabled
   */
  protected function getAllModules() {
    // ModuleHandler::getModuleDirectories() returns data only for installed
    // modules. system_rebuild_module_data() includes only the site's install
    // profile directory, while we may need to include a custom profile.
    // @see _system_rebuild_module_data().
    $listing = new ExtensionDiscovery(\Drupal::root());

    $profile_directories = [];
    // Register the install profile.
    $installed_profile = drupal_get_profile();
    if ($installed_profile) {
      $profile_directories[] = drupal_get_path('profile', $installed_profile);
    }
    if ($this->includeProfile) {
      // Add any profiles used in bundles.
      $assigner = \Drupal::service('features_assigner');
      $bundles = $assigner->getBundleList();
      foreach ($bundles as $bundle_name => $bundle) {
        if ($bundle->isProfile()) {
          // Register the profile directory.
          $profile_directory = 'profiles/' . $bundle->getProfileName();
          if (is_dir($profile_directory)) {
            $profile_directories[] = $profile_directory;
          }
        }
      }
    }
    $listing->setProfileDirectories($profile_directories);

    // Find modules.
    $modules = $listing->scan('module');

    // Find installation profiles.
    $profiles = $listing->scan('profile');

    foreach ($profiles as $key => $profile) {
      $modules[$key] = $profile;
    }

    return $modules;
  }

  /**
   * Returns a map of all config object names and their folders.
   *
   * The list is based on enabled modules and themes. The active configuration
   * storage is used rather than \Drupal\Core\Extension\ModuleHandler and
   *  \Drupal\Core\Extension\ThemeHandler in order to resolve circular
   * dependencies between these services and \Drupal\Core\Config\ConfigInstaller
   * and \Drupal\Core\Config\TypedConfigManager.
   *
   * @return array
   *   An array mapping config object names with directories.
   */
  public function getAllFolders() {
    if (!isset($this->folders)) {
      $this->folders = array();
      $this->folders += $this->getComponentNames('core', array('core'));

      $extensions = $this->configStorage->read('core.extension');
      // override the module list to include uninstalled modules (exported but not enabled)
      $extensions['module'] = $this->getAllModules();
      if (!empty($extensions['module'])) {
        $modules = $extensions['module'];
        if (!$this->includeProfile) {
          if ($install_profile = Settings::get('install_profile')) {
            unset($modules[$install_profile]);
          }
        }
        $this->folders += $this->getComponentNames('module', array_keys($modules));
      }

      // DO NOT OVERRIDE PROFILE if includeProfile is false
      // (which is the default in FeaturesInstallStorage)
      if ($this->includeProfile) {
        // The install profile can override module default configuration. We do
        // this by replacing the config file path from the module/theme with the
        // install profile version if there are any duplicates.
        $profile_folders = $this->getComponentNames('profile', array(drupal_get_profile()));
        $folders_to_replace = array_intersect_key($profile_folders, $this->folders);
        if (!empty($folders_to_replace)) {
          $this->folders = array_merge($this->folders, $folders_to_replace);
        }
      }
    }
    return $this->folders;
  }
}

