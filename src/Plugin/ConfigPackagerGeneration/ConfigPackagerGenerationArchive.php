<?php

/**
 * @file
 * Contains \Drupal\config_packager\Plugin\ConfigPackagerGeneration\ConfigPackagerGenerationArchive.
 */

namespace Drupal\config_packager\Plugin\ConfigPackagerGeneration;

use Drupal\Component\Serialization\Yaml;
use Drupal\config_packager\ConfigPackagerGenerationMethodBase;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class for generating a compressed archive of packages.
 *
 * @Plugin(
 *   id = \Drupal\config_packager\Plugin\ConfigPackagerGeneration\ConfigPackagerGenerationArchive::METHOD_ID,
 *   weight = -2,
 *   name = @Translation("Archive"),
 *   description = @Translation("Generate packages and optional profile as a compressed archive for download."),
 * )
 */
class ConfigPackagerGenerationArchive extends ConfigPackagerGenerationMethodBase {

  /**
   * The package generation method id.
   */
  const METHOD_ID = 'archive';

  /**
   * Overrides ConfigPackagerGenerationMethodInterface::prepare
   *
   * Read and merge in existing package files.
   */
  public function prepare($add_profile = FALSE, array $packages = array()) {
    // If no packages were specified, get all packages.
    if (empty($packages)) {
      $packages = $this->configPackagerManager->getPackages();
    }

    // If any packages exist, read in their files.
    $machine_names = $this->configPackagerManager->getPackageMachineNames(array_keys($packages));
    if ($existing_packages = $this->configPackagerManager->getPackageDirectories($machine_names, $add_profile)) {
      // Packages are keyed by short machine names while the existing packages
      // array is keyed by full machine names. Therefore, iterate the packages
      // to find matches.
      foreach ($packages as &$package) {
        if (isset($existing_packages[$package['machine_name']])) {
          $existing_directory = $existing_packages[$package['machine_name']];
          // Use the info file to determine the package's directory.
          $directory = $package['files']['info']['directory'];
          // Scan for all files.
          $files = file_scan_directory($existing_directory, '/.*/');
          foreach ($files as $file) {
            // Skip files in the install directory.
            if (strpos($existing_directory, InstallStorage::CONFIG_INSTALL_DIRECTORY) !== FALSE) {
              continue;
            }
            // Merge in the info file.
            if ($file->name == $package['machine_name'] . '.info') {
              $package['files']['info']['string'] = $this->mergeInfoFile($package['files']['info']['string'], $file->uri);
            }
            // Read in remaining files.
            else {
              // Determine if the file is within a subdirectory of the
              // extension's directory.
              $file_directory = dirname($file->uri);
              if ($file_directory !== $existing_directory) {
                $subdirectory = substr($file_directory, strlen($existing_directory) + 1);
              }
              else {
                $subdirectory = NULL;
              }
              $package['files'][] = [
                'filename' => $file->filename,
                'subdirectory' => $subdirectory,
                'directory' => $directory,
                'string' => file_get_contents($file->uri)
              ];
            }
          }
        }
      }
      // Clean up the $package pass by reference
      unset($package);
    }

    return $packages;
  }

  /**
   * {@inheritdoc}
   */
  public function generate($add_profile = FALSE, array $packages = array()) {
    // If no packages were specified, get all packages.
    if (empty($packages)) {
      $packages = $this->configPackagerManager->getPackages();
    }

    $return = [];

    // Remove any previous version of the exported archive.
    $machine_name = $this->configFactory->get('config_packager.settings')->get('profile.machine_name');
    $archive_name = file_directory_temp() . '/' . $machine_name . '.tar.gz';
    if (file_exists($archive_name)) {
      file_unmanaged_delete($archive_name);
    }

    $archiver = new ArchiveTar($archive_name);

    if ($add_profile) {
      $profile = $this->configPackagerManager->getProfile();
      $this->generatePackage($return, $profile, $archiver);
    }

    // Add package files.
    foreach ($packages as $package) {
      $this->generatePackage($return, $package, $archiver);
    }

    return $return;
  }

  /**
   * Write a package or profile's files to an archive.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   * @param ArchiveTar $archiver
   *   The archiver.
   */
  protected function generatePackage(array &$return, array $package, ArchiveTar $archiver) {
    $success = TRUE;
    foreach ($package['files'] as $file) {
      try {
        $this->generateFile($archiver, $file);
      }
      catch(\Exception $exception) {
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
   * Register a successful package or profile archive operation.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   */
  protected function success(array &$return, array $package) {
    $type = $package['type'] == 'module' ? $this->t('Package') : $this->t('Profile');
    $return[] = [
      'success' => TRUE,
      // Archive writing doesn't merit a message, and if done through the UI
      // would appear on the subsequent page load.
      'display' => FALSE,
      'message' => $this->t('!type @package written to archive.'),
      'variables' => [
        '!type' => $type,
        '@package' => $package['name']
      ],
    ];
  }

  /**
   * Register a failed package or profile archive operation.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   * @param Exception $exception
   *   The exception object.
   */
  protected function failure(&$return, array $package, Exception $exception) {
    $type = $package['type'] == 'package' ? $this->t('Package') : $this->t('Profile');
    $return[] = [
      'success' => FALSE,
      // Archive writing doesn't merit a message, and if done through the UI
      // would appear on the subsequent page load.
      'display' => FALSE,
      'message' => $this->t('!type @package not written to archive. Error: @error.'),
      'variables' => [
        '!type' => $type,
        '@package' => $package['name'],
        '@error' => $exception->getMessage()
      ],
    ];
  }

  /**
   * Write a file to the file system, creating its directory as needed.
   *
   * @param ArchiveTar $archiver
   *   The archiver.
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
  protected function generateFile(ArchiveTar $archiver, array $file) {
    $filename = $file['directory'];
    if (!empty($file['subdirectory'])) {
      $filename .= '/' . $file['subdirectory'];
    }
    $filename .= '/' . $file['filename'];
    if ($archiver->addString($filename, $file['string']) === FALSE) {
      throw new \Exception($this->t('Failed to archive file @filename.', ['@filename' => $file['filename']]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function exportFormSubmit(array &$form, FormStateInterface $form_state) {
    // Redirect to the archive file download.
    $form_state->setRedirect('config_packager.export_download');
  }

}
