<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesAssigner.
 */

namespace Drupal\features;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\features\FeaturesManagerInterface;
use Drupal\features\FeaturesBundle;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class responsible for performing package assignment.
 */
class FeaturesAssigner implements FeaturesAssignerInterface {
  use StringTranslationTrait;

  const DEFAULTBUNDLE = '_default_';

  /**
   * The package assignment method plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $assignerManager;

  /**
   * The features manager.
   *
   * @var \Drupal\features\FeaturesManagerInterface
   */
  protected $featuresManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Local cache for package assignment method instances.
   *
   * @var array
   */
  protected $methods;

  /**
   * Bundles.
   *
   * @var array of \Drupal\features\FeaturesBundleInterface
   */
  protected $bundles;

  /**
   * Currently active bundle.
   *
   * @var \Drupal\features\FeaturesBundleInterface
   */
  protected $currentBundle;

  /**
   * Constructs a new FeaturesAssigner object.
   *
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *    The features manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $assigner_manager
   *   The package assignment methods plugin manager.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The configuration factory.
   */
  public function __construct(FeaturesManagerInterface $features_manager, PluginManagerInterface $assigner_manager, EntityManagerInterface $entity_manager, ConfigFactoryInterface $config_factory, StorageInterface $config_storage) {
    $this->featuresManager = $features_manager;
    $this->assignerManager = $assigner_manager;
    $this->entityManager = $entity_manager;
    $this->configFactory = $config_factory;
    $this->configStorage = $config_storage;
    $this->bundles = $this->getBundleList();
    $this->currentBundle = $this->getBundle(self::DEFAULTBUNDLE);
    // Ensure bundle information is fresh.
    $this->createBundlesFromPackages();
  }

  /**
   * Initializes the injected features manager with the assigner.
   *
   * This should be called right after instantiating the assigner to make it
   * available to the features manager without introducing a circular
   * dependency.
   */
  public function initFeaturesManager() {
    $this->featuresManager->setAssigner($this);
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->methods = array();
    $this->featuresManager->reset();
  }

  /**
   * Gets enabled assignment methods.
   *
   * @return array
   *   An array of enabled assignment methods, sorted by weight.
   */
  public function getEnabledAssigners() {
    $enabled = $this->currentBundle->getEnabledAssignments();
    $weights = $this->currentBundle->getAssignmentWeights();
    foreach ($enabled as $key => $value) {
      $enabled[$key] = $weights[$key];
    }
    asort($enabled);
    return $enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function assignConfigPackages($force = FALSE) {
    foreach ($this->getEnabledAssigners() as $method_id => $info) {
      $this->applyAssignmentMethod($method_id, $force);
    }
  }

  /**
   * Applies a given package assignment method.
   *
   * @param string $method_id
   *   The string identifier of the package assignment method to use to package
   *   configuration.
   * @param bool $force
   *   (optional) If TRUE, assign config regardless of restrictions such as it
   *   being already assigned to a package.
   */
  protected function applyAssignmentMethod($method_id, $force = FALSE) {
    $this->getAssignmentMethodInstance($method_id)->assignPackages($force);
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignmentMethods() {
    return $this->assignerManager->getDefinitions();
  }

  /**
   * Returns an instance of the specified package assignment method.
   *
   * @param string $method_id
   *   The string identifier of the package assignment method to use to package
   *   configuration.
   *
   * @return \Drupal\features\FeaturesAssignmentMethodInterface
   */
  protected function getAssignmentMethodInstance($method_id) {
    if (!isset($this->methods[$method_id])) {
      $instance = $this->assignerManager->createInstance($method_id, array());
      $instance->setFeaturesManager($this->featuresManager);
      $instance->setAssigner($this);
      $instance->setEntityManager($this->entityManager);
      $instance->setConfigFactory($this->configFactory);
      $this->methods[$method_id] = $instance;
    }
    return $this->methods[$method_id];
  }

  /**
   * {@inheritdoc}
   */
  public function purgeConfiguration() {
    // Ensure that we are getting the defined package assignment information.
    // An invocation of \Drupal\Core\Extension\ModuleHandler::install() or
    // \Drupal\Core\Extension\ModuleHandler::uninstall() could invalidate the
    // cached information.
    $this->assignerManager->clearCachedDefinitions();
    $this->featuresManager->reset();
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle($name = NULL) {
    if (empty($name)) {
      return $this->currentBundle;
    }
    elseif (isset($this->bundles[$name])) {
      return $this->bundles[$name];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setBundle(FeaturesBundleInterface $bundle, $current = TRUE) {
    $this->bundles[$bundle->getMachineName()] = $bundle;
    if (isset($this->currentBundle) && ($current || ($bundle->getMachineName() == $this->currentBundle->getMachineName()))) {
      $this->currentBundle = $bundle;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function findBundle(array $info) {
    $bundle = NULL;
    if (!empty($info['features']['bundle'])) {
      $bundle = $this->getBundle($info['features']['bundle']);
    }
    elseif (!empty($info['package'])) {
      $bundle = $this->findBundleByName($info['package']);
    }
    if (!isset($bundle)) {
      // Return the default bundle.
      return $this->getBundle('');
    }
    return $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrent(FeaturesBundleInterface $bundle) {
    $this->currentBundle = $bundle;
    $session = \Drupal::request()->getSession();
    if (isset($session)) {
      $session->set('features_current_bundle', $bundle->getMachineName());
    }
    return $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleList() {
    if (empty($this->bundles)) {
      $this->bundles = array();
      $this->bundles[self::DEFAULTBUNDLE] = new FeaturesBundle('', $this->featuresManager, $this, $this->configFactory);
      $this->bundles[self::DEFAULTBUNDLE]->load();
      $bundles = array_keys($this->configFactory->get('features.bundles')->get());
      foreach ($bundles as $machine_name) {
        $bundle = new FeaturesBundle($machine_name, $this->featuresManager, $this, $this->configFactory);
        $bundle->load();
        $this->bundles[$machine_name] = $bundle;
      }
    }
    return $this->bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function findBundleByName($name, $create = FALSE) {
    $bundles = $this->getBundleList();
    foreach ($bundles as $machine_name => $bundle) {
      if ($name == $bundle->getName()) {
        return $bundle;
      }
    }
    $machine_name = strtolower(str_replace(array(' ', '-'), '_', $name));
    if (isset($bundles[$machine_name])) {
      return $bundles[$machine_name];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createBundle($name, $machine_name = NULL, $description = NULL, $is_profile = FALSE, $profile_name = NULL) {
    $bundle = new FeaturesBundle('', $this->featuresManager, $this, $this->configFactory);
    $bundle->load();
    if (empty($machine_name)) {
      $machine_name = strtolower(str_replace(array(' ', '-'), '_', $name));
    }
    $bundle->setMachineName($machine_name);
    $bundle->setName($name);
    if (isset($description)) {
      $bundle->setDescription($description);
    }
    else {
      $bundle->setDescription(t('Auto-generated bundle from package @name', array('@name' => $name)));
    }
    $bundle->setIsProfile($is_profile);
    if (isset($profile_name)) {
      $bundle->setProfileName($profile_name);
    }
    $bundle->save();
    $this->setBundle($bundle);
    return $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function createBundlesFromPackages() {
    $existing_bundles = $this->getBundleList();
    $new_bundles = [];
    $packages = $this->featuresManager->getExistingPackages();

    foreach ($packages as $machine_name => $package) {
      // Only parse from enabled features.
      if ($package['status'] === FeaturesManagerInterface::STATUS_ENABLED) {
        // Create a new bundle if:
        // - the feature specifies a bundle and
        // - that bundle doesn't yet exist locally.
        // Allow profiles to override previous values.
        if (!empty($package['features']['bundle']) &&
          !isset($existing_bundles[$package['features']['bundle']]) &&
          (!in_array($package['features']['bundle'], $new_bundles) || $package['type'] == 'profile')) {
          if ($package['type'] == 'profile') {
            $new_bundle = [
              'name' => $package['name'],
              'description' => $package['description'],
              'is_profile' => TRUE,
              'profile_name' => $machine_name,
            ];
          }
          else {
            $new_bundle = [
              'name' => isset($package['package']) ? $package['package'] : ucwords(str_replace('_', ' ', $package['features']['bundle'])),
              'description' => NULL,
              'is_profile' => FALSE,
              'profile_name' => NULL,
            ];
          }
          $new_bundle['machine_name'] = $package['features']['bundle'];
          $new_bundles[$new_bundle['machine_name']] = $new_bundle;
        }
      }
    }
    foreach ($new_bundles as $new_bundle) {
      $new_bundle = $this->createBundle($new_bundle['name'], $new_bundle['machine_name'], $new_bundle['description'], $new_bundle['is_profile']);
      drupal_set_message($this->t('Features bundle @name automatically created.', ['@name' => $new_bundle->getName()]));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getBundleOptions($default_name = NULL) {
    $list = $this->getBundleList();
    $result = array();
    foreach ($list as $machine_name => $bundle) {
      $result[$machine_name] = (isset($default_name) && $bundle->isDefault()) ? $default_name : $bundle->getName();
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function applyBundle($machine_name = NULL) {
    $this->reset();
    $bundle = $this->loadBundle($machine_name);
    if (isset($bundle)) {
      $this->assignConfigPackages();
      return $this->currentBundle;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function renameBundle($old_machine, $new_machine) {
    $is_current = (isset($this->currentBundle) && ($old_machine == $this->currentBundle->getMachineName()));
    $bundle = $this->getBundle($old_machine);
    if ($bundle->getMachineName() != '') {
      // Remove old bundle from the list if it's not the Default bundle.
      unset($this->bundles[$old_machine]);
    }
    $bundle->setMachineName($new_machine);
    $this->setBundle($bundle);
    // Put the bundle into the list with the correct name.
    $this->bundles[$bundle->getMachineName()] = $bundle;
    if ($is_current) {
      $this->setCurrent($bundle);
    }
    return $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function loadBundle($machine_name = NULL) {
    if (!isset($machine_name)) {
      $session = \Drupal::request()->getSession();
      if (isset($session)) {
        $machine_name = isset($session) ? $session->get('features_current_bundle', '') : '';
      }
    }
    $bundle = $this->getBundle($machine_name);
    if (!isset($bundle)) {
      // If bundle no longer exists then return default.
      $bundle = $this->bundles[self::DEFAULTBUNDLE];
    }
    return $this->setCurrent($bundle->load());
  }

  /**
   * {@inheritdoc}
   */
  public function removeBundle($machine_name) {
    $bundle = $this->getBundle($machine_name);
    if (isset($bundle) && !$bundle->isDefault()) {
      unset($this->bundles[$machine_name]);
      $bundle->remove();
    }
  }

}
