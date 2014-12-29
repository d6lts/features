<?php

/**
 * @file
 * Contains \Drupal\coinfig_packager\ConfigPackagerGenerationMethodManager.
 */

namespace Drupal\config_packager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages configuration packaging methods.
 */
class ConfigPackagerGenerationMethodManager extends DefaultPluginManager {

  /**
   * Constructs a new ConfigPackagerGenerationMethodManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   An object that implements CacheBackendInterface
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   An object that implements ModuleHandlerInterface
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/ConfigPackagerGeneration', $namespaces, $module_handler, 'Drupal\config_packager\ConfigPackagerGenerationMethodInterface');
    $this->cacheBackend = $cache_backend;
    $this->cacheKeyPrefix = 'config_packager_generation_methods';
    $this->cacheKey = 'config_packager_generation_methods';
    $this->alterInfo('config_packager_generation_info');
  }

}
