<?php

/**
 * @file
 * Contains \Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentProfile.
 */

namespace Drupal\features\Plugin\FeaturesAssignment;

use Drupal\features\FeaturesAssignmentMethodBase;
use Drupal\Core\Config\InstallStorage;

/**
 * Class for assigning configuration to packages based on configuration
 * dependencies.
 *
 * @Plugin(
 *   id = \Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentProfile::METHOD_ID,
 *   weight = -5,
 *   name = @Translation("Profile"),
 *   description = @Translation("Add configuration and other files to the optional install profile from the Drupal core Standard install profile. Without these additions, a generated install profile will be missing some important initial setup."),
 * )
 */
class FeaturesAssignmentProfile extends FeaturesAssignmentMethodBase {

  /**
   * The package assignment method id.
   */
  const METHOD_ID = 'profile';

  /**
   * {@inheritdoc}
   */
  public function assignPackages() {
    $profile = $this->featuresManager->getProfile();
    $package_directories = $this->featuresManager->listPackageDirectories(array(), TRUE);
    // Only read in from the Standard profile if this profile doesn't already
    // exist.
    if (!isset($package_directories[$profile['machine_name']])) {
      // Add configuration from the Standard profile.
      $config_collection = $this->featuresManager->getConfigCollection();
      $standard_directory = 'core/profiles/standard';
      $subdirectory = InstallStorage::CONFIG_INSTALL_DIRECTORY;
      $item_names = $this->listStandardConfig();
      foreach ($item_names as $item_name) {
        // If the configuration is present on the site, assign it.
        if (isset($config_collection[$item_name])) {
          $this->featuresManager->assignConfigPackage($profile['machine_name'], [$item_name]);
          // Reload the profile to refresh the config array after the addition.
          $profile = $this->featuresManager->getProfile();
        }
        // Otherwise, copy it over from Standard.
        else {
          $filename = $item_name . '.yml';
          $profile['files'][] = [
            'filename' => $filename,
            'subdirectory' => $subdirectory,
            'string' => file_get_contents($standard_directory . '/' . $subdirectory . '/' . $filename)
          ];
        }
      }

      // Add .profile and .install files from Standard.
      $files = [
        'install',
        'profile',
      ];
      // Iterate through the files.
      foreach ($files as $extension) {
        $filename = $standard_directory . '/standard.' . $extension;
        if (file_exists($filename)) {
          // Read the file contents.
          $string = file_get_contents($filename);
          // Substitute the profile's machine name and name for the Standard
          // profile's equivalents.
          $string = str_replace(
            ['standard', 'Standard'],
            [$profile['machine_name'], $profile['name']],
            $string
          );
          // Add the files to those to be output.
          $profile['files'][$extension] = [
            'filename' => $profile['machine_name'] . '.' . $extension,
            'subdirectory' => NULL,
            'string' => $string
          ];
        }
      }

      // Merge in module and theme dependencies from Standard.
      $info_file_uri = $standard_directory . '/standard.info.yml';
      if (file_exists($info_file_uri)) {
        $profile_info = \Drupal::service('info_parser')->parse($info_file_uri);
        // Merge in dependencies and themes data.
        $profile = $this->featuresManager->arrayMergeUnique($profile, $profile_info, ['dependencies', 'themes']);
      }
    }
    $this->featuresManager->setProfile($profile);
  }

  /**
   * Returns the list of configuration items required by the Standard install
   * profile.
   *
   * If install code is adapted from the Standard profile, these configuration
   * items will be required.
   *
   * @return array
   *   An array of configuration item names.
   */
  protected function listStandardConfig() {
    return [
      'contact.form.feedback',
      'user.role.administrator'
    ];
  }

}
