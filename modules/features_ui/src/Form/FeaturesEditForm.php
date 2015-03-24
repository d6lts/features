<?php

/**
 * @file
 * Contains \Drupal\features_ui\Form\FeaturesEditForm.
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
class FeaturesEditForm extends FormBase {

  /**
   * The features manager.
   *
   * @var array
   */
  protected $featuresManager;

  /**
   * The package assigner.
   *
   * @var array
   */
  protected $assigner;

  /**
   * The package generator.
   *
   * @var array
   */
  protected $generator;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a FeaturesSettingsForm object.
   *
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *   The features manager.
   */
  public function __construct(FeaturesManagerInterface $features_manager, FeaturesAssignerInterface $assigner, FeaturesGeneratorInterface $generator, ModuleHandlerInterface $module_handler) {
    $this->featuresManager = $features_manager;
    $this->assigner = $assigner;
    $this->generator = $generator;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('features.manager'),
      $container->get('features_assigner'),
      $container->get('features_generator'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'features_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $name = '') {
    $this->assigner->assignConfigPackages();
    $packages = $this->featuresManager->getPackages();
    if (empty($packages[$name])) {
      drupal_set_message(t('Feature !name does not exist.', array('!name' => $name)), 'error');
      return array();
    }
    $package = $packages[$name];

    $form['#feature'] = $package;
    $form['info'] = array(
      '#type' => 'fieldset',
      '#title' => t('General Information'),
      '#tree' => FALSE,
      '#weight' => 2,
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#prefix' => "<div id='features-export-info'>",
      '#suffix' => '</div>',
    );
    $form['info']['name'] = array(
      '#title' => t('Name'),
      '#description' => t('Example: Image gallery') . ' (' . t('Do not begin name with numbers.') . ')',
      '#type' => 'textfield',
      '#default_value' => $package['name'],
    );
    $form['info']['machine_name'] = array(
      '#type' => 'machine_name',
      '#title' => t('Machine-readable name'),
      '#description' => t('Example: image_gallery') . '<br/>' .
        t('NOTE: Do NOT include the namespace prefix; it will be added automatically. ') . t('May only contain lowercase letters, numbers and underscores.'),
      '#required' => TRUE,
      '#default_value' => $package['machine_name_short'],
      '#machine_name' => array(
        'source' => array('info', 'name'),
      ),
    );
    $form['info']['description'] = array(
      '#title' => t('Description'),
      '#description' => t('Provide a short description of what users should expect when they enable your feature.'),
      '#type' => 'textarea',
      '#rows' => 3,
      '#default_value' => $package['description'],
    );
    $form['info']['package'] = array(
      '#title' => t('Package'),
      '#description' => t('Organize your features in groups.'),
      '#type' => 'textfield',
      '#default_value' => $package['package'],
    );
    $form['info']['version'] = array(
      '#title' => t('Version'),
      '#description' => t('Examples: 7.x-1.0, 7.x-1.0-beta1'),
      '#type' => 'textfield',
      '#required' => FALSE,
      '#default_value' => $package['version'],
      '#size' => 30,
    );

    $generation_info = array();
    if (\Drupal::currentUser()->hasPermission('export configuration')) {
      // Offer available generation methods.
      $generation_info = $this->generator->getGenerationMethods();
      // Sort generation methods by weight.
      uasort($generation_info, '\Drupal\Component\Utility\SortArray::sortByWeightElement');
    }

    // build the Component Listing panel on the right
    //_features_export_form_components($form, $form_state);

    $form['actions'] = array('#type' => 'actions', '#tree' => TRUE);
    foreach ($generation_info as $method_id => $method) {
      $form['actions'][$method_id] = array(
        '#type' => 'submit',
        '#name' => $method_id,
        '#value' => $this->t('!name', array('!name' => $method['name'])),
        '#attributes' => array(
          'title' => String::checkPlain($method['description']),
        ),
      );
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->assigner->assignConfigPackages();

    $package = $form['#feature'];
    $profile = $this->featuresManager->getProfile();
    $package['name'] = $form_state->getValue('name');
    $package['machine_name_short'] = $form_state->getValue('machine_name');
    $prefix = !empty($profile['machine_name']) ? $profile['machine_name'] . '_' : '';
    $package['machine_name'] = $prefix . $package['machine_name_short'];
    $package['description'] = $form_state->getValue('description');
    $package['version'] = $form_state->getValue('version');
    $this->featuresManager->savePackage($package);
    dsm($profile);
    dsm($package);

    $method_id = NULL;
    $trigger = $form_state->getTriggeringElement();
    $op = $form_state->getValue('op');
    if (!empty($trigger) && empty($op)) {
      $method_id = $trigger['#name'];
    }

    if (!empty($method_id)) {
      $packages = array($package['machine_name']);
      $profile_settings = \Drupal::config('features.settings')->get('profile');
      if ($profile_settings['add']) {
        $this->generator->generateProfile($method_id, $packages, FALSE);
      }
      else {
        $this->generator->generatePackages($method_id, $packages, FALSE);
      }

      $this->generator->applyExportFormSubmit($method_id, $form, $form_state);
    }
  }

}
