<?php

/**
 * @file
 * Contains \Drupal\features_ui\Form\FeaturesSettingsForm.
 */

namespace Drupal\features_ui\Form;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Xss;
use Drupal\features\FeaturesAssignerInterface;
use Drupal\features\FeaturesGeneratorInterface;
use Drupal\features\FeaturesManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the features settings form.
 */
class FeaturesSettingsForm extends FormBase {

  /**
   * The features manager.
   *
   * @var array
   */
  protected $featuresManager;

  /**
   * Constructs a FeaturesSettingsForm object.
   *
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *   The features manager.
   */
  public function __construct(FeaturesManagerInterface $features_manager) {
    $this->featuresManager = $features_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('features.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'features_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $settings = $this->featuresManager->getSettings();

    $form['settings'] = array(
      '#type' => 'container',
      '#tree' => TRUE,
    );

    $form['settings']['conflicts'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow conflicts'),
      '#default_value' => $settings->get('conflicts'),
      '#description' => $this->t('Allow configuration to be exported to more than one feature.'),
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Save settings'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $this->featuresManager->getSettings();
    $values = $form_state->getValue('settings');
    foreach ($values as $key => $value) {
      $settings->set($key, $value);
    }
    $settings->save();
  }

}
