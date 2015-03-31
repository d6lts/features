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
    $profile_settings = $settings->get('profile');

    $form['settings'] = array(
      '#type' => 'container',
      '#tree' => TRUE,
    );

    $form['settings']['profile'] = array(
      '#type' => 'container',
      '#tree' => TRUE,
    );

    $form['settings']['profile']['name'] = array(
      '#title' => $this->t('Namespace'),
      '#type' => 'textfield',
      '#description' => $this->t('A unique human-readable name of packages namespace.'),
      '#default_value' => $profile_settings['name'],
    );

    $form['settings']['profile']['machine_name'] = array(
      '#title' => $this->t('Machine name'),
      '#type' => 'machine_name',
      '#required' => FALSE,
      '#default_value' => $profile_settings['machine_name'],
      '#description' => $this->t('A unique machine-readable name of packages namespace.  Used to prefix exported packages. It must only contain lowercase letters, numbers, and underscores.'),
      '#machine_name' => array(
        'source' => array('settings', 'profile', 'name'),
      ),
    );

    $form['settings']['conflicts'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow conflicts'),
      '#default_value' => $settings->get('conflicts'),
      '#description' => $this->t('Allow configuration to be exported to more than one feature.'),
    );

    $form['settings']['profile']['install_profile'] = array(
      '#title' => t('Installation Profile'),
      '#type' => 'fieldset',
      '#tree' => FALSE,
    );

    $form['settings']['profile']['install_profile']['add'] = array(
      '#type' => 'checkbox',
      '#title' => t('Include install profile'),
      '#default_value' => $profile_settings['add'],
      '#description' => $this->t('Select this option to have your configuration modules packaged into an install profile.'),
      '#attributes' => array(
        'data-add-profile' => 'status',
      ),
    );

    $show_if_profile_add_checked = array(
      'visible' => array(
        ':input[data-add-profile="status"]' => array('checked' => TRUE),
      ),
    );

    $form['settings']['profile']['install_profile']['name'] = array(
      '#title' => $this->t('Distribution name'),
      '#type' => 'textfield',
      '#default_value' => $profile_settings['name'],
      '#description' => $this->t('The human-readable name of your install profile or distribution.'),
      '#size' => 30,
      // Show only if the profile.add option is selected.
      '#states' => $show_if_profile_add_checked,
    );

    $form['settings']['profile']['install_profile']['description'] = array(
      '#title' => $this->t('Distribution description'),
      '#type' => 'textfield',
      '#default_value' => $profile_settings['description'],
      '#description' => $this->t('A description of your install profile or distribution.'),
      '#size' => 80,
      // Show only if the profile.add option is selected.
      '#states' => $show_if_profile_add_checked,
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
    $this->featuresManager->setNameSpace(
      $form_state->getValue(array('settings', 'profile', 'machine_name')),
      $form_state->getValue(array('settings', 'profile', 'name')),
      ''
    );
  }

}
