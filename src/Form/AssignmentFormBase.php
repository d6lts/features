<?php

/**
 * @file
 * Contains \Drupal\config_packager\Form\AssignmentFormBase.
 */

namespace Drupal\config_packager\Form;

use Drupal\config_packager\ConfigPackagerManagerInterface;
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
   * Stores the configuration object for config_packager.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Stores the configuration storage object for config_packager.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The configuration packager manager.
   *
   * @var \Drupal\config_packager\ConfigPackagerManagerInterface
   */
  protected $configPackagerManager;

  /**
   * Constructs a AssignmentBaseForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\config_packager\ConfigPackagerManagerInterface $config_packager_manager
   *   The configuration packager manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ConfigPackagerManagerInterface $config_packager_manager) {
    $this->configFactory = $config_factory;
    $this->configPackagerManager = $config_packager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config_packager.manager')
    );
  }

  /**
   * Adds configuration types checkboxes.
   */
  protected function setTypeSelect(&$form, $defaults, $type) {
    $options = $this->configPackagerManager->listConfigTypes();

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
