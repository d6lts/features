<?php

/**
 * @file
 * Contains \Drupal\config_packager\ConfigPackagerManager.
 */

namespace Drupal\config_packager;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\config_packager\ConfigPackagerAssignerInterface;
use Drupal\config_packager\ConfigPackagerGeneratorInterface;
use Drupal\config_packager\ConfigPackagerManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The ConfigPackagerManager provides helper functions for building packages.
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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Configuration Packager profile settings.
   *
   * @var array
   */
  protected $profileSettings;

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
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The target storage.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityManagerInterface $entity_manager, ConfigFactoryInterface $config_factory, StorageInterface $config_storage, ConfigManagerInterface $config_manager, ModuleHandlerInterface $module_handler) {
    $this->entityManager = $entity_manager;
    $this->configStorage = $config_storage;
    $this->configManager = $config_manager;
    $this->moduleHandler = $module_handler;
    $this->profileSettings = $config_factory->get('config_packager.settings')->get('profile');
    $this->packages = [];
    $this->initProfile();
    $this->configCollection = [];
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->packages = [];
    // Don't use getConfigCollection because reset() may be called in
    // cases where we don't need to load config.
    foreach ($this->configCollection as &$config) {
      $config['package'] = NULL;
    }
    // Clean up the $config pass by reference.
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
    // Ensure package names are current.
    $this->refreshPackageNames();
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
   * {@inheritdoc}
   */
  public function getGenerator() {
    return $this->generator;
  }

  /**
   * {@inheritdoc}
   */
  public function setGenerator(ConfigPackagerGeneratorInterface $generator) {
    $this->generator = $generator;
  }

  /**
   * Initializes the profile based on stored settings.
   */
  protected function initProfile() {
    if (empty($this->profile)) {
      $this->assignProfile(
        $this->profileSettings['machine_name'],
        $this->profileSettings['name'],
        $this->profileSettings['description']
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPackageDirectories(array $machine_names = array(), $add_profile = FALSE) {
    if (empty($machine_names)) {
      $machine_names = $this->getPackageMachineNames();
    }

    // If the add_profile argument was set, add the profile's machine name.
    if ($add_profile) {
      $profile = $this->getProfile();
      $machine_names[] = $profile['machine_name'];
    }

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
    // Register the profile directory.
    $profile_directory = 'profiles/' . $profile['machine_name'];
    if (is_dir($profile_directory)) {
      $profile_directories[] = $profile_directory;
    }
    $listing->setProfileDirectories($profile_directories);

    // Find modules.
    $modules = $listing->scan('module');

    // Find installation profiles.
    $profiles = $listing->scan('profile');

    foreach ($profiles as $key => $profile) {
      $modules[$key] = $profile;
    }

    // Filter to include only the requested packages.
    $modules = array_intersect_key($modules, array_fill_keys($machine_names, NULL));
    $directories = array();
    foreach ($modules as $name => $module) {
      $directories[$name] = $module->getPath();
    }

    return $directories;
  }

  /**
   * Sets the profile to a given machine_name, name, and description.
   */
  protected function assignProfile($machine_name, $name = NULL, $description = '') {
    $profile = $this->getProject($machine_name, $name, $description, 'profile');
    $this->setProfile($profile);
  }

  /**
   * {@inheritdoc}
   */
  public function initPackage($machine_name_short, $name = NULL, $description = '') {
    if (!isset($this->packages[$machine_name_short])) {
      $this->packages[$machine_name_short] = $this->getProject($machine_name_short, $name, $description);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function initCorePackage() {
    $machine_name_short = 'core';
    $name = $this->t('Core');
    $description = $this->t('Provide core components required by other configuration modules.');
    $this->initPackage($machine_name_short, $name, $description);
  }

  /**
   * {@inheritdoc}
   */
  public function assignConfigPackage($package_name, array $item_names) {
    $config_collection = $this->getConfigCollection();

    // Determine whether the profile is requested.
    $profile =& $this->profile;
    if ($package_name == $profile['machine_name']) {
      $package =& $profile;
    }
    // If not, a package is requested.
    else {
      $packages =& $this->packages;
      if (isset($packages[$package_name])) {
        $package =& $packages[$package_name];
      }
      else {
        throw new \Exception($this->t('Failed to assign @item_name to package %package_name. Package not found.', ['@item_name' => $item_name, '@package_name' => $package_name]));
      }
    }

    foreach ($item_names as $item_name) {
      if (!isset($config_collection[$item_name])) {
        throw new \Exception($this->t('Failed to assign @item_name to package %package_name. Configuration item not found.', ['@item_name' => $item_name, '@package_name' => $package_name]));
      }
      if (empty($config_collection[$item_name]['package']) && !in_array($item_name, $package['config'])) {
        // Add the item to the package's config array.
        $package['config'][] = $item_name;
        // Mark the item as already assigned.
        $config_collection[$item_name]['package'] = $package_name;
        // Set any module dependencies of the configuration item as package
        // dependencies.
        if (isset($config_collection[$item_name]['data']['dependencies']['module'])) {
          $dependencies =& $package['dependencies'];
          $dependencies = array_unique(array_merge($dependencies, $config_collection[$item_name]['data']['dependencies']['module']));
          sort($dependencies);
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
    foreach ($patterns as $pattern => $machine_name_short) {
      if (isset($this->packages[$machine_name_short])) {
        foreach ($config_collection as $item_name => $item) {
          if (empty($item['package']) && preg_match('/[_\-.]' . $pattern . '[_\-.]/', '.' . $item['name_short'] . '.')) {
            try {
              $this->assignConfigPackage($machine_name_short, [$item_name]);
            }
            catch(\Exception $exception) {
              \Drupal::logger('config_packager')->error($exception->getMessage());
            }
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
            try {
              $this->assignConfigPackage($config_collection[$item_name]['package'], [$dependent_item_name]);
            }
            catch(\Exception $exception) {
              \Drupal::logger('config_packager')->error($exception->getMessage());
            }
          }
        }
      }
    }
  }

  /**
   * Initializes and returns a package or profile array.
   *
   * @param string $machine_name_short
   *   Machine name of the package without a profile prefix.
   * @param string $name_short
   *   Human readable name of the package without a profile prefix.
   * @param string $description
   *   Description of the package.
   * @return array
   *   An array with the following keys:
   *   - 'machine_name': machine name of the project such as 'example_article'.
   *   - 'machine_name_short': short machine name of the project such as
   *     'article'.
   *   - 'name': human readable name of the project such as 'Example Article'.
   *   - 'name_short': short human readable name of the project such as
   *     'Article'.
   *   - 'description': description of the project.
   *   - 'type': type of Drupal project ('profile' or 'module').
   *   - 'core': Drupal core compatibility ('8.x'),
   *   - 'dependencies': array of module dependencies.
   *   - 'themes': array of names of themes to enable.
   *   - 'config': array of names of configuration items.
   *   - 'directory': the extension's directory.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'subdirectory': any subdirectory of the file within the extension
   *         directory.
   *      - 'string': the contents of the file.
   */
  protected function getProject($machine_name_short, $name_short = NULL, $description = '', $type = 'module') {
    $description = $description ?: $this->t('@name configuration.', ['@name' => $name_short]);
    $name_short = $this->getName($machine_name_short, $name_short);
    $project = [
      'machine_name' => $machine_name_short,
      'machine_name_short' => $machine_name_short,
      'name' => $name_short,
      'name_short' => $name_short,
      'description' => $description,
      'type' => $type,
      'core' => '8.x',
      'dependencies' => [],
      'themes' => [],
      'config' => [],
      'directory' => $machine_name_short,
      'files' => []
    ];
    if ($type == 'module') {
      $this->setPackageNames($project);
    }

    return $project;
  }

  /**
   * Refreshes names for all packages to ensure they reflect current profile
   * naming.
   *
   * The profile data (machine_name, name) may have changed since the package
   * was generated.
   */
  protected function refreshPackageNames() {
    $packages = $this->getPackages();
    foreach ($packages as &$package) {
      $this->setPackageNames($package);
    }
    // Clean up the $file pass by reference.
    unset($package);
    $this->setPackages($packages);
  }

  /**
   * Prefixes a package's short machine name and name with those of the
   * profile.
   *
   * @param array &$package
   *   A package array, passed by reference.
   */
  protected function setPackageNames(array &$package) {
    $profile = $this->getProfile();
    $package['machine_name'] = $profile['machine_name'] . '_' . $package['machine_name_short'];
    $package['name'] = $profile['name'] . ' ' . $package['name_short'];
  }

  /**
   * Generates and adds .info.yml files to a package.
   *
   * @param array $package
   *   The package.
   */
  protected function addInfoFile(array &$package) {
    // Filter to standard keys of the profiles that we will use in info files.
    $info_keys = [
      'name',
      'description',
      'type',
      'core',
      'dependencies',
      'themes'
    ];
    $info = array_intersect_key($package, array_fill_keys($info_keys, NULL));

    // Assign to a "package" named for the profile.
    $info['package'] = $this->profile['name'];

    // Add in the configuration in a form used by the Configuragion
    // Development (config_devel) module, allowing that module to update
    // configuration.
    // @see https://www.drupal.org/node/2300717.
    if (!empty($package['config'])) {
      $info['config_devel'] = $package['config'];
    }

    // Add profile-specific info data.
    if ($info['type'] == 'profile') {
      // Set the distribution name.
      $info['distribution'] = [
        'name' => $info['name']
      ];
    }

    $package['files']['info'] = [
      'filename' => $package['machine_name'] . '.info.yml',
      'subdirectory' => NULL,
      // Filter to remove any empty keys, e.g., an empty themes array.
      'string' => Yaml::encode(array_filter($info))
    ];
  }

  /**
   * Generates and adds files to the profile.
   */
  protected function addProfileFiles() {
    // Adjust file paths to include the profile.
    $packages = $this->getPackages();
    foreach ($packages as &$package) {
      $package['directory'] = $this->profile['directory'] . '/modules/custom/' . $package['directory'];
    }
    // Clean up the $package pass by reference.
    unset($package);
    $this->setPackages($packages);

    // Add the profile's files.
    $profile = $this->getProfile();
    $this->addInfoFile($profile);
    $this->addPackageFiles($profile) ;
    $this->setProfile($profile);
  }

  /**
   * Generates and adds files to all packages.
   */
  protected function addPackagesFiles() {
    $packages = $this->getPackages();
    foreach ($packages as &$package) {
      $this->addPackageFiles($package) ;
    }
    // Clean up the $package pass by reference.
    unset($package);
    $this->setPackages($packages);
  }

  /**
   * Generates and adds files to a given package or profile.
   */
  protected function addPackageFiles(array &$package) {
    $config_collection = $this->getConfigCollection();
    // Ensure the directory reflects the current full machine name.
    $package['directory'] = $package['machine_name'];
    // Only add files if there is at least one piece of configuration
    // present.
    if (!empty($package['config'])) {
      // Add .info.yml files.
      if ($package['type'] == 'module') {
        $this->addInfoFile($package);
      }
      // Add configuration files.
      foreach ($package['config'] as $name) {
        $config = $config_collection[$name];
        // The UUID is site-specfic, so don't export it.
        if ($entity_type_id = $this->configManager->getEntityTypeIdByName($name)) {
          unset($config['data']['uuid']);
        }
        $package['files'][$name] = [
          'filename'=> $config['name'] . '.yml',
          'subdirectory' => InstallStorage::CONFIG_INSTALL_DIRECTORY,
          'string' => Yaml::encode($config['data'])
        ];
      }
    }
  }
  /**
   * {@inheritdoc}
   */
  public function arrayMergeUnique(array $array1, array $array2, $keys = array()) {
    // If keys were specified, use only those.
    if (!empty($keys)) {
      $array2 = array_intersect_key($array2, array_fill_keys($keys, NULL));
    }

    // Iterate through the incoming array.
    foreach ($array2 as $key => $value) {
      // If its values are arrays, merge them in and sort them.
      if (is_array($value)) {
        $array1[$key] = array_unique(
          array_merge(
            $array1[$key],
            $value
          )
        );
        sort($array1[$key]);
      }
      // Otherwise, accept the incoming values.
      else {
        $array1[$key] = $value;
      }
    }

    return $array1;
  }

  /**
   * Returns a human readable name.
   *
   * If no human readable name is available, this method generates a default
   * one based on the machine name.
   *
   * @param string $machine_name
   *   Machine name of the package.
   * @param string $name
   *   Human readable name, if any, of the package.
   *
   * @return string
   *   Human readable name of the package.
   */
  protected function getName($machine_name, $name = NULL) {
    // Provide a default name based on the machine name.
    if (empty($name)) {
      $name = str_replace('_', ' ', $machine_name);
    }
    // Drupal extensions use title case.
    // @see https://www.drupal.org/node/1346158
    return ucwords($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getPackageMachineNames(array $machine_names_short = array(), $add_profile = FALSE) {
    $packages = $this->getPackages();

    // If specific names were requested, use only those.
    if (!empty($machine_names_short)) {
      $packages = array_intersect_key($packages, array_fill_keys($machine_names_short, NULL));
    }

    // Iterate through the packages for their machine names.
    $machine_names = [];
    foreach ($packages as $package) {
      $machine_names[] = $package['machine_name'];
    }

    return $machine_names;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackageMachineNamesShort(array $machine_names = array()) {
    $packages = $this->getPackages();

    // If no specific machine names were requested, return all.
    if (empty($machine_names)) {
      return array_keys($this->proifles);
    }

    // Iterate through the packages for their short machine names.
    $machine_names_short = [];
    foreach ($packages as $package) {
      if (in_array($package['machine_name'], $machine_names)) {
        $machine_names_short[] = $package['machine_name_short'];
      }
    }

    return $machine_names_short;
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
   * {@inheritdoc}
   */
  public function getModuleList(array $names = array(), $namespace = NULL) {
    $modules = $this->moduleHandler->getModuleList();
    if (!empty($names) || !empty($namespace)) {
      $return = [];

      // Detect modules by name.
      foreach ($names as $name) {
        if (!empty($name) && isset($modules[$name])) {
          $return[$name] = $modules[$name];
        }
      }

      // Detect modules by namespace.
      if (!empty($namespace)) {
        foreach ($modules as $module_name => $extension) {
          if (strpos($module_name, $namespace) === 0) {
            $return[$module_name] = $extension;
          }
        }
      }
      return $return;
    }
    return $modules;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensionConfig(Extension $extension) {
    $config_path = $extension->getPath() .  '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;

    if (is_dir($config_path)) {
      $install_storage = new FileStorage($config_path);
      return $install_storage->listAll();
    }

    return [];
  }

  /**
   * Gets stored configuration for a given configuration type.
   *
   * @param string $config_type
   *   The type of configuration.
   */
  protected function getConfigByType($config_type) {
    // For a given entity type, load all entities.
    if ($config_type && $config_type !== ConfigPackagerManagerInterface::SYSTEM_SIMPLE_CONFIG) {
      $entity_storage = $this->entityManager->getStorage($config_type);
      $names = [];
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
   * Loads configuration from storage into a property.
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
            'name_short' => $item_name,
            'label' => $label,
            'type' => $config_type,
            'data' => $data,
            // @todo: use // ConfigDependencyManager::getDependentEntities('config', $name) ?
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
   * {@inheritdoc}
   */
  public function prepareFiles($add_profile = FALSE) {
    // Add package files first so their filename values can be altered to nest
    // them in a profile.
    $this->addPackagesFiles();
    if ($add_profile) {
      $this->addProfileFiles();
    }
  }

}
