<?php

/**
 * @file
 * Contains \Drupal\config_packager\Form\AssignmentFormBase.
 */

namespace Drupal\config_packager\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\config_packager\ConfigPackagerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the selected configuration assignment method for this site.
 */
abstract class AssignmentFormBase extends FormBase {

  /**
   * Stores the configuration object for config_packager.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configFactory;

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
   * Add a configuration types select.
   */
  protected function setTypeSelect(&$form, $defaults) {
    $options = $this->configPackagerManager->getConfigTypes();

    $form['types'] = array(
      '#type' => 'select',
      '#title' => $this->t('Types'),
      '#description' => $this->t('Select the types of configuration that should be considered base types.'),
      '#options' => $options,
      '#default_value' => $defaults,
      '#multiple' => TRUE,
    );
  }

  /**
   * Add a "Save settings" submit action.
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
