<?php

/**
 * @file
 * Contains \Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentProfile.
 */

namespace Drupal\features\Plugin\FeaturesAssignment;

use Drupal\features\FeaturesAssignmentMethodBase;
use Drupal\Core\Config\InstallStorage;

/**
 * Class for adding configuration for the optional install profile.
 *
 * @Plugin(
 *   id = \Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentProfile::METHOD_ID,
 *   weight = -5,
 *   name = @Translation("Profile"),
 *   description = @Translation("Add configuration and other files to the optional install profile from the Drupal core Standard install profile. Without these additions, a generated install profile will be missing some important initial setup."),
 *   config_route_name = "features.assignment_profile"
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
    $current_bundle = $this->assigner->getBundle();

    if ($current_bundle->isProfile()) {
      $settings = $current_bundle->getAssignmentSettings(self::METHOD_ID);

      // Ensure the profile package exists.
      $profile_name = $current_bundle->getProfileName();
      $profile_package = $this->featuresManager->getPackage($profile_name);
      if (empty($profile_package)) {
        $this->featuresManager->initPackage($profile_name, $current_bundle->getName(), $current_bundle->getDescription(), 'profile');
      }

      // Assign configuration by type.
      $this->assignPackageByConfigTypes(self::METHOD_ID, $profile_name);

      // Only read in from the Standard profile if this profile doesn't already
      // exist.
      $package_directories = $this->featuresManager->listPackageDirectories(array(), $current_bundle);
      if (!isset($package_directories[$profile_name])) {
        $standard_directory = 'core/profiles/standard';
        // Conditionally add files from the 'standard' install profile.
        if ($settings['standard']['files']) {
          // Add configuration from the Standard profile.
          $config_collection = $this->featuresManager->getConfigCollection();
          $subdirectory = InstallStorage::CONFIG_INSTALL_DIRECTORY;
          $item_names = $this->listRequiredStandardConfig();
          foreach ($item_names as $item_name) {
            // If the configuration is present on the site, assign it.
            if (isset($config_collection[$item_name])) {
              // Only assign it if it's not already assigned to a package.
              if (empty($config_collection[$item_name]['package'])) {
                $this->featuresManager->assignConfigPackage($profile_name, [$item_name]);
                // Reload the profile to refresh the config array after the addition.
                $profile_package = $this->featuresManager->getPackage($profile_name);
              }
              // If it's already assigned, add a dependency.
              else {
                $machine_name = $current_bundle->getFullName($config_collection[$item_name]['package']);
                if (!in_array($machine_name, $config_collection[$item_name]['dependencies'])) {
                  $config_collection[$item_name]['dependencies'][] = $machine_name;
                }
              }
            }
            // Otherwise, copy it over from Standard.
            else {
              $filename = $item_name . '.yml';
              $profile_package['files'][] = [
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
                [$profile_name, $current_bundle->getName()],
                $string
              );
              // Add the files to those to be output.
              $profile_package['files'][$extension] = [
                'filename' => $profile_name . '.' . $extension,
                'subdirectory' => NULL,
                'string' => $string
              ];
            }
          }
        }
        // Conditionally merge in module and theme dependencies from the
        // 'standard' install profile.
        if ($settings['standard']['dependencies']) {
          $info_file_uri = $standard_directory . '/standard.info.yml';
          if (file_exists($info_file_uri)) {
            $profile_info = \Drupal::service('info_parser')->parse($info_file_uri);
            // Merge in dependencies and themes data.
            $profile_package = $this->featuresManager->arrayMergeUnique($profile_package, $profile_info, ['dependencies', 'themes']);
          }
        }
        $this->featuresManager->savePackage($profile_package);
      }
    }
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
  protected function listRequiredStandardConfig() {
    return [
      'contact.form.feedback',
      'user.role.administrator'
    ];
  }

}
