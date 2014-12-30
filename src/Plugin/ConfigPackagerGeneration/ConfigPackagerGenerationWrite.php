<?php

/**
 * @file
 * Contains \Drupal\config_packager\Plugin\ConfigPackagerGeneration\ConfigPackagerGenerationWrite.
 */

namespace Drupal\config_packager\Plugin\ConfigPackagerGeneration;

use Drupal\config_packager\ConfigPackagerGenerationMethodBase;
use Drupal\Core\Writer\WriteTar;

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
   * {@inheritdoc}
   */
  public function prepare($add_profile = FALSE, array $packages = array()) {

  }

  /**
   * {@inheritdoc}
   */
  public function generate($add_profile = FALSE, array $packages = array()) {
    // If no packages were specified, get all packages.
    if (empty($packages)) {
      $packages = $this->configPackagerManager->getPackages();
    }

    // If it's a profile, write it to the 'profiles' directory. Otherwise,
    // it goes in 'modules/custom'.
    $base_directory = $add_profile ? 'profiles' : 'modules/custom';

    $return = [];

    // Add profile files.
    if ($add_profile) {
      $profile = $this->configPackagerManager->getProfile();
      $this->generatePackage($return, $profile, $base_directory);
    }

    // Add package files.
    foreach ($packages as $package) {
      $this->generatePackage($return, $package, $base_directory);
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
   * @param string $base_directory
   *   The base directory.
   */
  protected function generatePackage(array &$return, array $package, $base_directory) {
    $success = TRUE;
    foreach ($package['files'] as $file) {
      try {
        $this->generateFile($base_directory, $file);
      }
      catch(Exception $exception) {
        $this->failure($return, $package, $base_directory, $exception);
        $success = FALSE;
        break;
      }
    }
    if ($success) {
      $this->success($return, $package, $base_directory);
    }
  }

  /**
   * Register a successful package or profile write operation.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   * @param string $base_directory
   *   The base directory.
   */
  protected function success(&$return, $package, $base_directory) {
    $directory = $base_directory . '/' . $package['files']['info']['directory'];
    $type = $package['type'] == 'module' ? $this->t('Package') : $this->t('Profile');
    $return[] = [
      'success' => TRUE,
      'display' => TRUE,
      'message' => $this->t('!type @package written to @directory.'),
      'variables' => [
        '!type' => $type,
        '@package' => $package['name'],
        '@directory' => $directory
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
   * @param string $base_directory
   *   The base directory.
   * @param Exception $exception
   *   The exception object.
   */
  protected function failure(&$return, $package, $base_directory, Exception $exception) {
    $directory = $base_directory . '/' . dirname($package['files']['info']['filename']);
    $type = $package['type'] == 'package' ? $this->t('Package') : $this->t('Profile');
    $return[] = [
      'success' => FALSE,
      'display' => TRUE,
      'message' => $this->t('!type @package not written to @directory. Error: @error.'),
      'variables' => [
        '!type' => $type,
        '@package' => $package['name'],
        '@directory' => $directory,
        '@error' => $exception->getMessage()
      ],
    ];
  }

  /**
   * Write a file to the file system, creating its directory as needed.
   *
   * @param string $base_directory
   *   Directory to prepend to file path.
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
  protected function generateFile($base_directory, $file) {
    $directory = $base_directory . '/' . $file['directory'];
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
