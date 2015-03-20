<?php

/**
 * @file
 * Contains \Drupal\features_ui\Form\AssignmentFormBase.
 */

namespace Drupal\features_ui\Form;

use Drupal\features\FeaturesManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures the selected configuration assignment method for this site.
 */
abstract class AssignmentFormBase extends FormBase {

  /**
   * Stores the configuration object for features.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Stores the configuration storage object for features.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The features manager.
   *
   * @var \Drupal\features\FeaturesManagerInterface
   */
  protected $featuresManager;

  /**
   * Constructs a AssignmentBaseForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *   The features manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FeaturesManagerInterface $features_manager) {
    $this->configFactory = $config_factory;
    $this->featuresManager = $features_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('features.manager')
    );
  }

  /**
   * Adds configuration types checkboxes.
   */
  protected function setTypeSelect(&$form, $defaults, $type) {
    $options = $this->featuresManager->listConfigTypes();

    $form['types'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Types'),
      '#description' => $this->t('Select types of configuration that should be considered !type types.', array('!type' => $type)),
      '#options' => $options,
      '#default_value' => $defaults,
    );
  }

  /**
   * Adds a "Save settings" submit action.
   */
  protected function setActions(&$form) {
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Save settings'),
    );
  }

}
