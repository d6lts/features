<?php

/**
 * @file
 * Contains \Drupal\config_packager\Plugin\ConfigPackagerGeneration\ConfigPackagerGenerationWrite.
 */

namespace Drupal\config_packager\Plugin\ConfigPackagerGeneration;

use Drupal\config_packager\ConfigPackagerGenerationMethodBase;
use Drupal\Core\Config\InstallStorage;

/**
 * Class for writing packages to the local file system.
 *
 * @Plugin(
 *   id = \Drupal\config_packager\Plugin\ConfigPackagerGeneration\ConfigPackagerGenerationWrite::METHOD_ID,
 *   weight = 2,
 *   name = @Translation("Write"),
 *   description = @Translation("Write packages and optional profile to the file system."),
 * )
 */
class ConfigPackagerGenerationWrite extends ConfigPackagerGenerationMethodBase {

  /**
   * The package generation method id.
   */
  const METHOD_ID = 'write';

  /**
   * Read and merge in existing files for a given package or profile.
   */
  protected function preparePackage($add_profile, &$package, $existing_packages) {
    // If it's a profile, write it to the 'profiles' directory. Otherwise, it
    // goes in 'modules/custom'.
    $base_directory = $add_profile ? 'profiles' : 'modules/custom';

    // If this package is already present, prepare files.
    if (isset($existing_packages[$package['machine_name']])) {
      $existing_directory = $existing_packages[$package['machine_name']];

      // Reassign all files to the extension's directory.
      foreach ($package['files'] as &$file) {
        $file['directory'] = $existing_directory;
      }
      // Clean up the $file pass by reference
      unset($file);

      // Merge in the info file.
      $info_file_uri = $existing_directory . '/' . $package['machine_name'] . '.info.yml';
      if (file_exists($info_file_uri)) {
        $package['files']['info']['string'] = $this->mergeInfoFile($package['files']['info']['string'], $info_file_uri);
      }

      // Remove the config directory, as it will be replaced.
      $config_directory = $existing_directory . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
      if (is_dir($config_directory)) {
        file_unmanaged_delete_recursive($config_directory);
      }
    }
    // If the package is not present, nest its files in the base directory.
    else {
      // Prepend all file directories with the base directory.
      foreach ($package['files'] as &$file) {
        $file['directory'] = $base_directory . '/' . $file['directory'];
      }
      // Clean up the $file pass by reference
      unset($file);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generate($add_profile = FALSE, array $profile = array(), array $packages = array()) {
    // If no packages were specified, get all packages.
    if (empty($packages)) {
      $packages = $this->configPackagerManager->getPackages();
    }

    $return = [];

    // Add profile files.
    if ($add_profile) {
      // If no profile was passed, load the profile.
      if (empty($profile)) {
        $profile = $this->configPackagerManager->getProfile();
      }
      $this->generatePackage($return, $profile);
    }

    // Add package files.
    foreach ($packages as $package) {
      $this->generatePackage($return, $package);
    }
    return $return;
  }

  /**
   * Write a package or profile's files to the file system.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   */
  protected function generatePackage(array &$return, array $package) {
    $success = TRUE;
    foreach ($package['files'] as $file) {
      try {
        $this->generateFile($file);
      }
      catch(Exception $exception) {
        $this->failure($return, $package, $exception);
        $success = FALSE;
        break;
      }
    }
    if ($success) {
      $this->success($return, $package);
    }
  }

  /**
   * Register a successful package or profile write operation.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   */
  protected function success(&$return, $package) {
    $type = $package['type'] == 'module' ? $this->t('Package') : $this->t('Profile');
    $return[] = [
      'success' => TRUE,
      'display' => TRUE,
      'message' => $this->t('!type @package written to @directory.'),
      'variables' => [
        '!type' => $type,
        '@package' => $package['name'],
        '@directory' => $package['files']['info']['directory']
      ],
    ];
  }

  /**
   * Register a failed package or profile write operation.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   * @param Exception $exception
   *   The exception object.
   */
  protected function failure(&$return, $package, Exception $exception) {
    $type = $package['type'] == 'package' ? $this->t('Package') : $this->t('Profile');
    $return[] = [
      'success' => FALSE,
      'display' => TRUE,
      'message' => $this->t('!type @package not written to @directory. Error: @error.'),
      'variables' => [
        '!type' => $type,
        '@package' => $package['name'],
        '@directory' => $package['files']['info']['directory'],
        '@error' => $exception->getMessage()
      ],
    ];
  }

  /**
   * Write a file to the file system, creating its directory as needed.
   *
   * @param array $file
   *   Array with the following keys:
   *   - 'filename': the name of the file.
   *   - 'subdirectory': any subdirectory of the file within the extension
   *      directory.
   *   - 'directory': the extension directory of the file.
   *   - 'string': the contents of the file.
   *
   * @throws Exception
   */
  protected function generateFile($file) {
    $directory = $file['directory'];
    if (!empty($file['subdirectory'])) {
      $directory .= '/' . $file['subdirectory'];
    }
    if (!is_dir($directory)) {
      if (drupal_mkdir($directory, NULL, TRUE) === FALSE) {
        throw new \Exception($this->t('Failed to create directory @directory.', ['@directory' => $directory]));
      }
    }
    if (file_put_contents($directory . '/' . $file['filename'], $file['string']) === FALSE) {
      throw new \Exception($this->t('Failed to write file @filename.', ['@filename' => $file['filename']]));
    }
  }

}
