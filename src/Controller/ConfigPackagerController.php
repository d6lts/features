<?php

/**
 * @file
 * Contains \Drupal\config_packager\Controller\ConfigPackagerController
 */

namespace Drupal\config_packager\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\system\FileDownloadController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for config module routes.
 */
class ConfigPackagerController implements ContainerInjectionInterface {

  /**
   * The file download controller.
   *
   * @var \Drupal\system\FileDownloadController
   */
  protected $fileDownloadController;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      new FileDownloadController()
    );
  }

  /**
   * Constructs a ConfigController object.
   *
   * @param \Drupal\system\FileDownloadController $file_download_controller
   *   The file download controller.
   */
  public function __construct(FileDownloadController $file_download_controller) {
    $this->fileDownloadController = $file_download_controller;
  }

  /**
   * Downloads a tarball of the site configuration.
   */
  public function downloadExport() {
    $archive_name = \Drupal::config('config_packager.settings')->get('profile.machine_name') . '.tar.gz';
    $request = new Request(array('file' => $archive_name));
    return $this->fileDownloadController->download($request, 'temporary');
  }

}