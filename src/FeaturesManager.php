<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesManager.
 */

namespace Drupal\features;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\features\FeaturesAssignerInterface;
use Drupal\features\FeaturesGeneratorInterface;
use Drupal\features\FeaturesManagerInterface;
use Drupal\features\FeaturesInstallStorage;
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
 * The FeaturesManager provides helper functions for building packages.
 */
class FeaturesManager implements FeaturesManagerInterface {
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
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Features settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * The Features assignment settings.
   *
   * @var array
   */
  protected $assignmentSettings;

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
   * The list of defined package sets
   * @var array
   */
  protected $package_sets;

  /**
   * The install profile, also used as the "package" value in info files.
   *
   * @var array
   */
  protected $profile;

  /**
   * The package assigner.
   *
   * @var \Drupal\features\FeaturesAssigner
   */
  protected $assigner;

  /**
   * Constructs a FeaturesManager object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The target storage.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   * @param \Drupal\features\FeaturesAssignerInterface $assigner
   *   The features assigner.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityManagerInterface $entity_manager, ConfigFactoryInterface $config_factory,
                              StorageInterface $config_storage, ConfigManagerInterface $config_manager,
                              ModuleHandlerInterface $module_handler) {
    $this->entityManager = $entity_manager;
    $this->configStorage = $config_storage;
    $this->configManager = $config_manager;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->settings = $config_factory->getEditable('features.settings');
    $this->assignmentSettings = $config_factory->getEditable('features.assignment');
    $this->packages = [];
    $this->package_sets = NULL;
    $this->initProfile();
    $this->configCollection = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName($type, $name) {
    if ($type == FeaturesManagerInterface::SYSTEM_SIMPLE_CONFIG || !$type) {
      return $name;
    }

    $definition = $this->entityManager->getDefinition($type);
    $prefix = $definition->getConfigPrefix() . '.';
    return $prefix . $name;
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->packages = [];
    $this->package_sets = NULL;
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
  public function getConfigCollection($reset = FALSE) {
    $this->initConfigCollection($reset);
    return $this->configCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function applyNamespace($namespace = NULL) {
    if (isset($namespace)) {
      if (!isset($this->packages_sets)) {
        // Need to compute the list of package sets from existing config modules
        $this->assigner->assignConfigPackages();
        $this->refreshPackageNames();
      }
      if ($namespace == $this->profile['machine_name']) {
        // return if no namespace change is needed
        return;
      }
      $this->profile['machine_name'] = $namespace;
      if (isset($this->package_sets[$namespace])) {
        $this->profile['name'] = $this->package_sets[$namespace]['name'];
        $this->profile['description'] = $this->package_sets[$namespace]['description'];
      }
    }
    if (isset($this->configCollection)) {
      // force recalculation of config if already created
      $this->reset();
      $this->getConfigCollection(TRUE);
    }
    // Now recompute the packages based on the namespace
    $this->assigner->assignConfigPackages();
    $this->refreshPackageNames();
  }

  /**
   * {@inheritdoc}
   */
  public function setNameSpace($namespace = NULL, $name = '', $description = '') {
    if (isset($namespace)) {
      $this->profile['name'] = !empty($name) ? $name : $namespace;
      $this->profile['machine_name'] = $namespace;
      $this->profile['description'] = '';
      if (isset($this->package_sets[$namespace])) {
        if (empty($name)) {
          $this->profile['name'] = $this->package_sets[$namespace]['name'];
        }
        if (empty($description)) {
          $this->profile['description'] = $this->package_sets[$namespace]['description'];
        }
      }
      $this->profile['machine_name_short'] = $this->profile['machine_name'];
      $this->profile['name_short'] = $this->profile['name'];
    }
    $session = \Drupal::request()->getSession();
    $session->set('features_namespace_name', $this->profile['name']);
    $session->set('features_namespace_machine_name', $this->profile['machine_name']);
    $session->set('features_namespace_description', $this->profile['description']);
  }

  /**
   * {@inheritdoc}
   */
  public function getNameSpace() {
    $session = \Drupal::request()->getSession();
    $machine_name = $session->get('features_namespace_machine_name');
    if (!empty($machine_name)) {
      $this->profile['name'] = $session->get('features_namespace_name');
      $this->profile['name_short'] = $session->get('features_namespace_name');
      $this->profile['machine_name'] = $machine_name;
      $this->profile['machine_name_short'] = $machine_name;
      $this->profile['description'] = $session->get('features_namespace_description');
    }
    return $machine_name;
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
  public function savePackage(array &$package) {
    if (!empty($package['machine_name_short'])) {
      $this->addPackageFiles($package);
      $this->packages[$package['machine_name_short']] = $package;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function filterPackages(array $packages) {
    $profile = $this->getProfile();
    $namespace = $profile['machine_name'];
    $result = array();
    foreach ($packages as $key => $package) {
      if (empty($namespace) || (strpos($package['machine_name'], $namespace) === 0) || ($package['machine_name'] == $package['machine_name_short'])) {
        $result[$key] = $package;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackageSets() {
    if (!isset($this->package_sets)) {
      $this->refreshPackageNames();
    }
    return $this->package_sets;
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
  public function setAssigner(FeaturesAssignerInterface $assigner) {
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
  public function setGenerator(FeaturesGeneratorInterface $generator) {
    $this->generator = $generator;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportSettings() {
    return $this->settings->get('export');
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignmentSettings() {
    return $this->assignmentSettings;
  }

  /**
   * Initializes the profile based on stored settings.
   */
  protected function initProfile() {
    if (empty($this->profile)) {
      $profileSettings = $this->settings->get('profile');
      $this->assignProfile(
        $profileSettings['machine_name'],
        $profileSettings['name'],
        $profileSettings['description']
      );
    }
  }

  /**
   * Return the name of an extension
   * @param mixed $extension
   *   The string name of an extension, or a full Extension object
   * @return string
   */
  protected function getExtensionName($extension) {
    if (is_string($extension)) {
      return $extension;
    }
    else {
      return $extension->getName();
    }
  }

  /**
   * Return the path to an extension info.yml file
   * @param mixed $extension
   *   The string name of an extension, or a full Extension object
   * @param string $type
   *   The type of extension
   * @return string
   */
  protected function getExtensionPath($extension, $type = 'module') {
    if (is_string($extension)) {
      return drupal_get_filename($type, $extension);
    }
    else {
      return $extension->getPathname();
    }
  }

  /**
   * Return the contents of an extensions info.yml file
   * @param mixed $extension
   *   The string name of an extension, or a full Extension object
   * @return array info.yml data
   */
  protected function getExtensionInfo($extension, $type = 'module') {
    $info_file_uri = $this->getExtensionPath($extension, $type);
    return \Drupal::service('info_parser')->parse($info_file_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function isFeatureModule($module) {
    $info = $this->getExtensionInfo($module);
    return isset($info['features']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFeatureName($info) {
    // The original machine_name_short was stored in the "features" key in the info file.
    // Also support having the name stored as a subkey of the "features" config section.
    $feature_name = $info['features'];
    if (is_array($feature_name)) {
      $feature_name = $feature_name['name'];
    }
    return $feature_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getExistingPackages($enabled = FALSE, $namespace = NULL) {
    $result = array();
    if ($enabled) {
      $modules = $this->moduleHandler->getModuleList();
    }
    else {
      // ModuleHandler::getModuleList() returns data only for installed modules.
      // We want to search all possible exports for Features that might be disabled
      $listing = new ExtensionDiscovery(\Drupal::root());
      $modules = $listing->scan('module');
    }
    foreach ($modules as $name => $module) {
      if (empty($namespace) || (strpos($name, $namespace) === 0)) {
        if ($this->isFeatureModule($module)) {
          $result[$name] = $this->getExtensionInfo($module);
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function listPackageDirectories(array $machine_names = array(), $add_profile = FALSE) {
    if (empty($machine_names)) {
      $machine_names = $this->listPackageMachineNames();
    }

    // If the add_profile argument was set, add the profile's machine name.
    if ($add_profile) {
      $profile = $this->getProfile();
      if (!empty($profile['machine_name'])) {
        $machine_names[] = $profile['machine_name'];
      }
    }

    $modules = $this->getAllModules($add_profile);
    // Filter to include only the requested packages.
    $modules = array_intersect_key($modules, array_fill_keys($machine_names, NULL));
    $directories = array();
    foreach ($modules as $name => $module) {
      $directories[$name] = $module->getPath();
    }

    return $directories;
  }

  /**
   * Return a list of modules regardless of if they are enabled
   * @param bool $add_profile
   *   determine if custom profile is also included
   */
  public function getAllModules($add_profile = FALSE, $namespace = NULL) {
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
    if ($add_profile) {
      $profile = $this->getProfile();
      if (!empty($profile['machine_name'])) {
        // Register the profile directory.
        $profile_directory = 'profiles/' . $profile['machine_name'];
        if (is_dir($profile_directory)) {
          $profile_directories[] = $profile_directory;
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

    $return = array();
    // Detect modules by namespace.
    // If namespace is provided but is empty, then match all modules
    foreach ($modules as $module_name => $extension) {
      if (empty($namespace) || (strpos($module_name, $namespace) === 0)) {
        $return[$module_name] = $extension;
      }
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function assignProfile($machine_name, $name = NULL, $description = '') {
    $profile = $this->getProject($machine_name, $name, $description, 'profile');
    $this->setProfile($profile);
  }

  /**
   * {@inheritdoc}
   */
  public function initPackage($machine_name_short, $name = NULL, $description = '') {
    if (!isset($this->packages[$machine_name_short])) {
      return $this->packages[$machine_name_short] = $this->getProject($machine_name_short, $name, $description);
    }
    return NULL;
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
  public function assignConfigPackage($package_name, array $item_names, $force = FALSE) {
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
        throw new \Exception($this->t('Failed to package %package_name. Package not found.', ['@package_name' => $package_name]));
      }
    }

    foreach ($item_names as $item_name) {
      if (!isset($config_collection[$item_name])) {
        throw new \Exception($this->t('Failed to assign @item_name to package @package_name. Configuration item not found.', ['@item_name' => $item_name, '@package_name' => $package_name]));
      }
      if (($force || empty($config_collection[$item_name]['package'])) && !in_array($item_name, $package['config'])) {
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
              \Drupal::logger('features')->error($exception->getMessage());
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function assignConfigDependents(array $item_names = NULL, $package = NULL) {
    $config_collection = $this->getConfigCollection();
    if (empty($item_names)) {
      $item_names = array_keys($config_collection);
    }
    foreach ($item_names as $item_name) {
      if (!empty($config_collection[$item_name]['package'])) {
        foreach ($config_collection[$item_name]['dependents'] as $dependent_item_name) {
          if (isset($config_collection[$dependent_item_name]) && (!empty($package) || empty($config_collection[$dependent_item_name]['package']))) {
            try {
              $package_name = !empty($package) ? $package : $config_collection[$item_name]['package'];
              // If a Package is specified, force assign it to the given package.
              $this->assignConfigPackage($package_name, [$dependent_item_name], !empty($package));
            }
            catch(\Exception $exception) {
              \Drupal::logger('features')->error($exception->getMessage());
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
   *   - 'status': the status of the project module
   *   - 'directory': the extension's directory.
   *   - 'files' array of files, each having the following keys:
   *      - 'filename': the name of the file.
   *      - 'subdirectory': any subdirectory of the file within the extension
   *         directory.
   *      - 'string': the contents of the file.
   */
  protected function getProject($machine_name_short, $name_short = NULL, $description = '', $type = 'module') {
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
      'status' => FeaturesManagerInterface::STATUS_DEFAULT,
      'version' => '',
      'state' => FeaturesManagerInterface::STATE_DEFAULT,
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
    $this->package_sets = [];
    // Add current profile to package sets.
    if (!empty($this->profile['machine_name'])) {
      $set = array(
        'name' => $this->profile['name'],
        'description' => $this->profile['description']
      );
      $this->package_sets[$this->profile['machine_name']] = $set;
    }
    $profile = $this->settings->get('profile');
    if (!empty($profile['machine_name']) && ($profile['machine_name'] != $this->profile['machine_name'])) {
      // Ensure profile stored in settings is also in package sets
      $set = array(
        'name' => $profile['name'],
        'description' => $profile['description']
      );
      $this->package_sets[$profile['machine_name']] = $set;
    }
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
   * Also fill in module-specific properties, such as status, version
   *
   * @param array &$package
   *   A package array, passed by reference.
   */
  protected function setPackageNames(array &$package) {
    $module_list = $this->getAllModules();
    if (isset($module_list[$package['machine_name']])) {
      $package['status'] = $this->moduleHandler->moduleExists($package['machine_name'])
        ? FeaturesManagerInterface::STATUS_ENABLED
        : FeaturesManagerInterface::STATUS_DISABLED;
      //TODO: Determine module version
      $info = $this->getExtensionInfo($package['machine_name']);
      if (!empty($info)) {
        $package['version'] = $info['version'];
        $package['package'] = $info['package'];
      }
    }
    $this->updatePackageSet($package);
  }

  /**
   * Update or create the $package_set entry for this package
   * @param array $package
   */
  protected function updatePackageSet(array $package) {
    if ($package['machine_name'] != $package['machine_name_short']) {
      $set_name = substr($package['machine_name'], 0, strlen($package['machine_name']) - strlen($package['machine_name_short']) - 1);
      if (empty($this->package_sets[$set_name])) {
        $set = array(
          'name' => $package['package'],
          'description' => '',
        );
        $this->package_sets[$set_name] = $set;
      }
    }
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
      'themes',
      'version'
    ];
    $info = array_intersect_key($package, array_fill_keys($info_keys, NULL));

    // Assign to a "package" named for the profile.
    $info['package'] = $this->profile['name'];

    if (!empty($package['config'])) {
      // Save the current machine_name_short in the info file so the package
      // can be reloaded later by the AssignmentPackages plugin.
      $info['features'] = array(
        'name' => $package['machine_name_short']
      );
      if (!empty($package['excluded'])) {
        $info['features']['excluded'] = $package['excluded'];
      }
      else {
        unset($info['features']['excluded']);
      }
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
      $this->addPackageFiles($package);
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
        // User roles include all permissions currently assigned to them. To
        // avoid extraneous additions, reset permissions.
        if ($config['type'] == 'user_role') {
          $config['data']['permissions'] = [];
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
      if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
        $array1[$key] = array_unique(
          array_merge(
            $array1[$key],
            $value
          )
        );
        asort($array1[$key]);
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
  public function listPackageMachineNames(array $machine_names_short = array(), $add_profile = FALSE) {
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
  public function listPackageMachineNamesShort(array $machine_names = array()) {
    $packages = $this->getPackages();

    // If no specific machine names were requested, return all.
    if (empty($machine_names)) {
      return array_keys($this->packages);
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
  public function listConfigTypes() {
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
      FeaturesManagerInterface::SYSTEM_SIMPLE_CONFIG => $this->t('Simple configuration'),
    ] + $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleList(array $names = array(), $namespace = NULL) {
    // get all modules regardless of enabled/disabled
    $modules = $this->getAllModules();
    if (!empty($names) || !empty($namespace)) {
      $return = [];

      // Detect modules by name.
      foreach ($names as $name) {
        if (!empty($name) && isset($modules[$name])) {
          $return[$name] = $modules[$name];
        }
      }

      // Detect modules by namespace.
      // If namespace is provided but is empty, then match all modules
      if (isset($namespace)) {
        foreach ($modules as $module_name => $extension) {
          if (empty($namespace) || (strpos($module_name, $namespace) === 0)) {
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
  public function listExtensionConfig($extension) {
    $extension_storage = new FeaturesInstallStorage($this->configStorage);
    $name = $this->getExtensionName($extension);
    return array_keys($extension_storage->getComponentNames('module', array($name)));
  }

  /**
   * {@inheritdoc}
   */
  public function listConfigByType($config_type) {
    // For a given entity type, load all entities.
    if ($config_type && $config_type !== FeaturesManagerInterface::SYSTEM_SIMPLE_CONFIG) {
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
  protected function initConfigCollection($reset = FALSE) {
    if ($reset || empty($this->configCollection)) {
      $config_collection = [];
      $config_types = $this->listConfigTypes();
      foreach (array_keys($config_types) as $config_type) {
        $config = $this->listConfigByType($config_type);
        foreach ($config as $item_name => $label) {
          $name = $this->getFullName($config_type, $item_name);
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

  /**
   * {@inheritdoc}
   */
  public function getExportInfo($package, $add_profile = FALSE, $profile = NULL) {
    if (empty($profile)) {
      $profile = $this->getProfile();
    }
    $exportSettings = $this->getExportSettings();

    $full_name = $package['machine_name'];

    if ($add_profile) {
      // adjust export directory to be in profile
      $path = 'profiles/' . $profile['directory'] . '/modules';
    }
    else {
      $path = 'modules';
    }
    if (!empty($exportSettings['folder'])) {
      $path .= '/' . $exportSettings['folder'];
    }

    // prepend the namespace of the current profile
    if (!empty($profile['machine_name'])) {
      $full_name = $profile['machine_name'] . '_' . $package['machine_name_short'];
    }

    return array($full_name, $path);
  }

  /**
   * {@inheritdoc}
   */
  public function detectOverrides($feature) {
    $config_diff = \Drupal::service('config_update.config_diff');
    $extension_storage = new FeaturesInstallStorage($this->configStorage);

    $different = array();
    foreach ($feature['config'] as $name) {
      $active = $this->configStorage->read($name);
      $extension = $extension_storage->read($name);
      $extension = !empty($extension) ? $extension : array();
      if (!$config_diff->same($extension, $active)) {
        $different[] = $name;
      }
    }

    if (!empty($different)) {
      $feature['state'] = FeaturesManagerInterface::STATE_OVERRIDDEN;
    }
    return $different;
  }

  /**
   * {@inheritdoc}
   */
  public function statusLabel($status) {
    switch ($status) {
      case FeaturesManagerInterface::STATUS_NO_EXPORT:
        return t('Not exported');
      case FeaturesManagerInterface::STATUS_DISABLED:
        return t('Uninstalled');
      case FeaturesManagerInterface::STATUS_ENABLED:
        return t('Enabled');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function stateLabel($state) {
    switch ($state) {
      case FeaturesManagerInterface::STATE_DEFAULT:
        return t('Default');
      case FeaturesManagerInterface::STATE_OVERRIDDEN:
        return t('Changed');
    }
  }

}
