<?php

/**
 * @file
 * Contains \Drupal\features\FeaturesAssignmentMethodBase.
 */

namespace Drupal\features;

use Drupal\features\FeaturesManagerInterface;
use Drupal\features\FeaturesAssignerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for package assignment methods.
 */
abstract class FeaturesAssignmentMethodBase implements FeaturesAssignmentMethodInterface {
  use StringTranslationTrait;

  /**
   * The features manager.
   *
   * @var \Drupal\features\FeaturesManagerInterface
   */
  protected $featuresManager;

  /**
   * The features assigner.
   *
   * @var \Drupal\features\FeaturesAssignerInterface
   */
  protected $assigner;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public function setfeaturesManager(FeaturesManagerInterface $features_manager) {
    $this->featuresManager = $features_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function setAssigner(FeaturesAssignerInterface $assigner) {
    $this->assigner = $assigner;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityManager(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * Creates a package and assigns it configuration of the types specified in
   * a setting.
   *
   * @param string $method_id
   *   The ID of an assignment method.
   * @param string $machine_name
   *   Machine name of the package to be created.
   * @param string $name
   *   Machine name of the package to be created.
   * @param string $description
   *   Machine name of the package to be created.
   */
  protected function assignPackageByConfigTypes($method_id, $machine_name, $name, $description) {
    $current_bundle = $this->assigner->getBundle();
    $settings = $current_bundle->getAssignmentSettings($method_id);
    $types = $settings['types']['config'];

    $config_collection = $this->featuresManager->getConfigCollection();

    $initialized = FALSE;
    foreach ($config_collection as $item_name => $item) {
      if (in_array($item['type'], $types) && !isset($item['package'])) {
        if (!$initialized) {
          $this->featuresManager->initPackage($machine_name, $name, $description);
          $initialized = TRUE;
        }
        try {
          $this->featuresManager->assignConfigPackage($machine_name, [$item_name]);
        }
        catch (\Exception $exception) {
          \Drupal::logger('features')->error($exception->getMessage());
        }
      }
    }
  }

  /**
   * Assigns a given subdirectory to configuration of specified types.
   *
   * @param string $method_id
   *   The ID of an assignment method.
   * @param string $subdirectory
   *   The subdirectory that designated configuration should be exported to.
   */
  protected function assignSubdirectoryByConfigTypes($method_id, $subdirectory) {
    $current_bundle = $this->assigner->getBundle();
    $settings = $current_bundle->getAssignmentSettings($method_id);
    $types = $settings['types']['config'];

    $config_collection = $this->featuresManager->getConfigCollection();

    foreach ($config_collection as &$item) {
      if (in_array($item['type'], $types)) {
        $item['subdirectory'] = $subdirectory;
      }
    }
    unset($item);
    $this->featuresManager->setConfigCollection($config_collection);
  }

}
