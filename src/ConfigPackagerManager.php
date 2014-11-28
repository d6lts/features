<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerManager.
 */

namespace Drupal\config_packager;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Component\Serialization\Yaml;
use Drupal\config_packager\ConfigPackagerAssignerInterface;
use Drupal\config_packager\ConfigPackagerManagerInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The ConfigPackagerManager provides helper functions for building package.
 */
class ConfigPackagerManager implements ConfigPackagerManagerInterface {
  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The target storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The configuration present on the site.
   *
   * @var array
   */
  private $configCollection;

  /**
   * The packages to be generated.
   *
   * @var array
   */
  protected $packages;

  /**
   * The install profile, also used as the "package" value in info files.
   *
   * @var array
   */
  protected $profile;

  /**
   * The package assigner.
   *
   * @var \Drupal\config_packager\ConfigPackagerAssigner
   */
  protected $assigner;

  /**
   * Constructs a ConfigPackagerManager object.
   *
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The target storage.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, StorageInterface $config_storage, ConfigManagerInterface $config_manager) {
    $this->entityManager = $entity_manager;
    $this->configStorage = $config_storage;
    $this->configManager = $config_manager;
    $this->packages = [];
    $this->initProfile();
    $this->configCollection = [];
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->packages = array();
    // Don't use getConfigCollection because reset() may be called in
    // cases where we don't need to load config.
    foreach ($this->configCollection as &$config) {
      $config['package'] = NULL;
    }
    // Clean up the pass by reference
    unset($config);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigCollection() {
    $this->initConfigCollection();
    return $this->configCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigCollection(array $config_collection) {
    $this->configCollection = $config_collection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackages() {
    return $this->packages;
  }

  /**
   * {@inheritdoc}
   */
  public function setPackages(array $packages) {
    $this->packages = $packages;
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile() {
    return $this->profile;
  }

  /**
   * {@inheritdoc}
   */
  public function setProfile(array $profile) {
    $this->profile = $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssigner() {
    return $this->assigner;
  }

  /**
   * {@inheritdoc}
   */
  public function setAssigner(ConfigPackagerAssignerInterface $assigner) {
    $this->assigner = $assigner;
    $this->reset();
  }

  /**
   * Initialize the profile based on stored settings.
   */
  protected function initProfile() {
    if (empty($this->profile)) {
      $packager_settings = \Drupal::config('config_packager.settings');
      $machine_name = $packager_settings->get('profile.machine_name');
      $name = $packager_settings->get('profile.name');
      $description = $packager_settings->get('profile.description');
      $this->assignProfile($machine_name, $name, $description);
    }
  }

  /**
   * Set the profile to a given machine_name, name, and description.
   */
  protected function assignProfile($machine_name, $name = NULL, $description = '') {
    $this->profile = $this->getProject($machine_name, $name, $description, 'profile');
  }

  /**
   * {@inheritdoc}
   */
  public function initPackage($machine_name, $name = NULL, $description = '') {
    if (!isset($this->packages[$machine_name])) {
      $this->packages[$machine_name] = $this->getProject($machine_name, $name, $description);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function initCorePackage() {
    $machine_name = 'core';
    $name = $this->t('Core');
    $description = $this->t('Provide core components required by other configuration modules.');
    $this->initPackage($machine_name, $name, $description);
  }

  /**
   * {@inheritdoc}
   */
  public function assignConfigPackage($package_name, array $item_names) {
    $config_collection = $this->getConfigCollection();
    foreach ($item_names as $item_name) {
      if (empty($config_collection[$item_name]['package']) && !in_array($item_name, $this->packages[$package_name]['config'])) {
        // Add the item to the package's config array.
        $this->packages[$package_name]['config'][] = $item_name;
        // Mark the item as already assigned.
        $config_collection[$item_name]['package'] = $package_name;
        // Set any module dependencies of the configuration item as package
        // dependencies.
        if (isset($config_collection[$item_name]['data']['dependencies']['module'])) {
          $dependencies =& $this->packages[$package_name]['dependencies'];
          $dependencies = array_unique(array_merge($dependencies, $config_collection[$item_name]['data']['dependencies']['module']));
        }
      }
    }
    $this->setConfigCollection($config_collection);
  }

  /**
   * {@inheritdoc}
   */
  public function assignConfigByPattern(array $patterns) {
    $config_collection = $this->getConfigCollection();
    // Reverse sort by key so that child package will claim items before parent
    // package. E.g., event_registration will claim before event.
    krsort($config_collection);
    foreach ($patterns as $pattern => $machine_name) {
      if (isset($this->packages[$machine_name])) {
        foreach ($config_collection as $item_name => $item) {
          if (empty($item['package']) && preg_match('/[_\-.]' . $pattern . '[_\-.]/', $item['short_name'])) {
            $this->assignConfigPackage($machine_name, [$item_name]);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function assignConfigDependents(array $item_names = NULL) {
    $config_collection = $this->getConfigCollection();
    if (empty($item_names)) {
      $item_names = array_keys($config_collection);
    }
    foreach ($item_names as $item_name) {
      if (!empty($config_collection[$item_name]['package'])) {
        foreach ($config_collection[$item_name]['dependents'] as $dependent_item_name) {
          if (isset($config_collection[$dependent_item_name]) && empty($config_collection[$dependent_item_name]['package'])) {
            $this->assignConfigPackage($config_collection[$item_name]['package'], [$dependent_item_name]);
          }
        }
      }
    }
  }

  /**
   * Initialize and return a package or profile array.
   *
   * @param string $machine_name
   *   Machine name of the package.
   * @param string $name
   *   Human readable name of the package.
   * @param string $description
   *   Description of the package.
   * @return array
   *   An array with the following keys:
   *   - 'machine_name': machine name of the project.
   *   - 'name': human readable name of the project.
   *   - 'description': description of the project.
   *   - 'type': type of Drupal project ('profile' or 'module').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'config': array of names of configuration items.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'string': the contents of the file.
   */
  protected function getProject($machine_name, $name = NULL, $description = '', $type = 'module') {
    $description = $description ?: $this->t('@name configuration.', array('@name' => $name));
    return [
      'machine_name' => $machine_name,
      'name' => $this->getName($machine_name, $name),
      'description' => $description,
      'type' => $type,
      'core' => '8.x',
      'dependencies' => [],
      'config' => [],
      'files' => []
    ];
  }

  /**
   * Generate and add .info.yml files to a package.
   *
   * @param array $package
   *   The package.
   */
  protected function addInfoFile(&$package) {
    $machine_name = $package['machine_name'];
    // Filter to standard keys of the profiles that we will use in info files.
    $info_keys = array(
      'name',
      'description',
      'type',
      'core',
      'dependencies'
    );
    $info = array_intersect_key($package, array_combine($info_keys, $info_keys));

    // Assign to a "package" named for the profile.
    $info['package'] = $this->profile['name'];

    // Add in the configuration in a form used by the Configuragion
    // Development (config_devel) module, allowing that module to update
    // configuration.
    // @see https://www.drupal.org/node/2300717.
    if (!empty($package['config'])) {
      $info['config_devel'] = $package['config'];
    }

    // Prefix modules with the profile name.
    if ($info['type'] == 'module') {
      $machine_name = $this->profile['machine_name'] . '_' . $machine_name;
      $info['name'] = $this->profile['name'] . ' ' . $info['name'];
    }
    $package['files']['info'] = [
      'filename' => $machine_name . '/' . $machine_name . '.info.yml',
      // Filter to remove any empty keys, e.g., an empty dependencies array.
      'string' => Yaml::encode(array_filter($info))
    ];
  }

  /**
   * Generate and add files to the profile.
   */
  protected function addProfileFiles() {
    foreach ($this->packages as &$package) {
      foreach ($package['files'] as &$file) {
        $file['filename'] = $this->profile['machine_name'] . '/modules/custom/' . $file['filename'];
      }
      // Clean up the $file pass by reference.
      unset($file);
    }
    // Clean up the $package pass by reference.
    unset($package);
    $this->addInfoFile($this->profile);
  }

  /**
   * Generate and add files to all packages.
   */
  protected function addPackageFiles() {
    $config_collection = $this->getConfigCollection();
    foreach ($this->packages as $machine_name => &$package) {
      // Only add files if there is at least one piece of configuration
      // present.
      if (!empty($package['config'])) {
        // Add .info.yml files.
        $this->addInfoFile($package);
        // Add configuration files.
        foreach ($package['config'] as $name) {
          $config = $config_collection[$name];
          // The UUID is site-specfic, so don't export it.
          if ($entity_type_id = $this->configManager->getEntityTypeIdByName($name)) {
            unset($config['data']['uuid']);
          }
          $package['files'][$name] = [
            'filename'=> $this->profile['machine_name'] . '_' . $package['machine_name'] . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY . '/' . $config['name'] . '.yml',
            'string' => Yaml::encode($config['data'])
          ];
        }
      }
    }
    // Clean up the pass by reference.
    unset($package);
  }

  /**
   * Return a human readable name.
   *
   * If no human readable name is available, generate a default one based on
   * the machine name.
   *
   * @param string $machine_name
   *   Machine name of the package.
   * @param string $name
   *   Human readable name, if any, of the package.
   * @return string
   *   Human readable name of the package.
   */
  protected function getName($machine_name, $name = NULL) {
    // Provide a default name based on the machine name.
    if (empty($name)) {
      $name = ucwords(str_replace('_', ' ', $machine_name));
    }
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigTypes() {
    $definitions = [];
    foreach ($this->entityManager->getDefinitions() as $entity_type => $definition) {
      if ($definition->isSubclassOf('Drupal\Core\Config\Entity\ConfigEntityInterface')) {
        $definitions[$entity_type] = $definition;
      }
    }
    $entity_types = array_map(function (EntityTypeInterface $definition) {
      return $definition->getLabel();
    }, $definitions);
    // Sort the entity types by label, then add the simple config to the top.
    uasort($entity_types, 'strnatcasecmp');
    return [
      ConfigPackagerManagerInterface::SYSTEM_SIMPLE_CONFIG => $this->t('Simple configuration'),
    ] + $entity_types;
  }

  /**
   * Get stored configuration for a given configuration type.
   */
  protected function getConfigByType($config_type) {
    // For a given entity type, load all entities.
    if ($config_type && $config_type !== ConfigPackagerManagerInterface::SYSTEM_SIMPLE_CONFIG) {
      $entity_storage = $this->entityManager->getStorage($config_type);
      foreach ($entity_storage->loadMultiple() as $entity) {
        $entity_id = $entity->id();
        $label = $entity->label() ?: $entity_id;
        $names[$entity_id] = $label;
      }
    }
    // Handle simple configuration.
    else {
      $definitions = [];
      foreach ($this->entityManager->getDefinitions() as $entity_type => $definition) {
        if ($definition->isSubclassOf('Drupal\Core\Config\Entity\ConfigEntityInterface')) {
          $definitions[$entity_type] = $definition;
        }
      }
      // Gather the config entity prefixes.
      $config_prefixes = array_map(function (EntityTypeInterface $definition) {
        return $definition->getConfigPrefix() . '.';
      }, $definitions);

      // Find all config, and then filter our anything matching a config prefix.
      $names = $this->configStorage->listAll();
      $names = array_combine($names, $names);
      foreach ($names as $item_name) {
        foreach ($config_prefixes as $config_prefix) {
          if (strpos($item_name, $config_prefix) === 0) {
            unset($names[$item_name]);
          }
        }
      }
    }
    return $names;
  }

  /**
   * Load configuration from storage into a property.
   */
  protected function initConfigCollection() {
    if (empty($this->configCollection)) {
      $config_collection = [];
      $config_types = $this->getConfigTypes();
      foreach (array_keys($config_types) as $config_type) {
        $config = $this->getConfigByType($config_type);
        foreach ($config as $item_name => $label) {
          // Determine the full config name for the selected config entity.
          if ($config_type !== ConfigPackagerManagerInterface::SYSTEM_SIMPLE_CONFIG) {
            $definition = $this->entityManager->getDefinition($config_type);
            $name = $definition->getConfigPrefix() . '.' . $item_name;
          }
          // The config name is used directly for simple configuration.
          else {
            $name = $item_name;
          }
          $data = $this->configStorage->read($name);
          $config_collection[$name] = [
            'name' => $name,
            'short_name' => $item_name,
            'label' => $label,
            'type' => $config_type,
            'data' => $data,
            'dependents' => []
          ];
        }
      }
      // Add dependency information. The definition of each piece of
      // configuration includes the other configuration it's dependent on. Here
      // we add reverse dependencies: the configuration that is dependent on
      // a given piece of configuration.
      foreach ($config_collection as $config) {
        if (isset($config['data']['dependencies']['config'])) {
          foreach ($config['data']['dependencies']['config'] as $name) {
            if (isset($config_collection[$name])) {
              $config_collection[$name]['dependents'][] = $config['name'];
            }
          }
        }
      }
      $this->setConfigCollection($config_collection);
    }
  }

  /**
   * Iterate through packages and profile and prepare file names and contents.
   */
  protected function prepareFiles() {
    // Add package files first so their filename values can be altered to nest
    // them in a profile.
    $this->addPackageFiles();
    // @todo: make profile optional.
    $this->addProfileFiles();
  }

  /**
   * {@inheritdoc}
   */
  public function generate() {
    // Prepare the files.
    $this->prepareFiles();
  
    // Remove any previous version of the exported archive.
    $archive_name = file_directory_temp() . '/' . \Drupal::config('config_packager.settings')->get('profile.machine_name') . '.tar.gz';
    if (file_exists($archive_name)) {
      file_unmanaged_delete($archive_name);
    }

    $archiver = new ArchiveTar($archive_name);

    // Add profile files, if any.
    foreach ($this->profile['files'] as $file)  {
      $archiver->addString($file['filename'], $file['string']);
    }

    // Add package files.
    foreach ($this->packages as $package) {
      foreach ($package['files'] as $file)  {
        $archiver->addString($file['filename'], $file['string']);
      }
    }
  }

}
