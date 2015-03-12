<?php

/**
 * @file
 * Contains \Drupal\features\Form\FeaturesExportForm.
 */

namespace Drupal\features\Form;

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
 * Defines the configuration export form.
 */
class FeaturesExportForm extends FormBase {

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
   * Constructs a FeaturesExportForm object.
   *
   * @param \Drupal\Core\Config\StorageInterface $target_storage
   *   The target storage.
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
    return 'features_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->assigner->assignConfigPackages();
    $packages = $this->featuresManager->getPackages();
    $config_collection = $this->featuresManager->getConfigCollection();
    // Add in unpackaged configuration items.
    $this->addUnpackaged($packages, $config_collection);
    $config_types = $this->featuresManager->listConfigTypes();
    // Add dependencies.
    $config_types['dependencies'] = $this->t('Dependencies');
    uasort($config_types, 'strnatcasecmp');
    $features_config = \Drupal::config('features.settings');
    $module_names = array();

    $form['profile'] = array(
      '#type' => 'container',
      '#tree' => TRUE,
    );

    $profile_settings = $features_config->get('profile');
    $form['profile']['name'] = array(
      '#title' => $this->t('Namespace'),
      '#type' => 'textfield',
      '#default_value' => $profile_settings['name'],
      '#description' => $this->t('The human-readable name of a set of configuration modules. This name will also be used for an install profile if "Include install profile" is selected below.'),
      '#required' => TRUE,
      '#size' => 30,
    );

    $form['profile']['machine_name'] = array(
      '#type' => 'machine_name',
      '#maxlength' => 64,
      '#machine_name' => array(
        'source' => array('profile', 'name'),
      ),
      '#default_value' => $profile_settings['machine_name'],
      '#description' => $this->t('A unique machine-readable name of a set of configuration modules. This name will also be used for an install profile if "Include install profile" is selected below. It must only contain lowercase letters, numbers, and underscores.'),
      '#required' => TRUE,
    );

    $form['profile']['add'] = array(
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

    $form['profile']['description'] = array(
      '#title' => $this->t('Distribution description'),
      '#type' => 'textfield',
      '#default_value' => $profile_settings['description'],
      '#description' => $this->t('A description of your install profile or distribution.'),
      '#size' => 30,
      // Show only if the profile.add option is selected.
      '#states' => $show_if_profile_add_checked,
    );

    // Offer a preview of the packages.
    $form['preview'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Preview packages'),
    );
    foreach ($packages as $package) {
      // Bundle package configuration by type.
      $package_config = array();
      foreach ($package['config'] as $item_name) {
        $item = $config_collection[$item_name];
        $package_config[$item['type']][] = array(
          'name' => String::checkPlain($item_name),
          'label' => String::checkPlain($item['label']),
        );
      }
      // Add dependencies.
      if (!empty($package['dependencies'])) {
        $package_config['dependencies'] = array();
        foreach ($package['dependencies'] as $dependency) {
          if (!isset($module_names[$dependency])) {
            $module_names[$dependency] = $this->moduleHandler->getName($dependency);
          }
          $package_config['dependencies'][] = array(
            'name' => $dependency,
            'label' => $module_names[$dependency],
          );
        }
      }
      $form['preview'][$package['machine_name']] = array(
        '#type' => 'container',
        '#attributes' => array('class' => array('features-items-wrapper')),
      );
      $rows = array();
      // Use sorted array for order.
      foreach ($config_types as $type => $label) {
        // For each component type, offer alternating rows.
        if (isset($package_config[$type])) {
          // First, the component type label, as a header.
          $rows[][] = array(
            'data' => array(
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => String::checkPlain($label),
              '#attributes' => array('title' => String::checkPlain($type)),
            ),
            'header' => TRUE,
          );
          // Then the list of items of that type.
          $rows[][] = array(
            'data' => array(
              '#theme' => 'features_items',
              '#items' => $package_config[$type],
            ),
            'class' => 'item',
          );
        }
      }
      $form['preview'][$package['machine_name']]['items'] = array(
        '#type' => 'table',
        '#header' => array($this->t('@name: !description', array('@name' => $package['name'], '!description' => XSS::filterAdmin($package['description'])))),
        '#attributes' => array('class' => array('features-items')),
        '#rows' => $rows,
      );
    }
    $form['#attached'] = array(
      'library' => array(
        'features/drupal.features.admin',
      ),
    );
    // Offer available generation methods.
    $generation_info = $this->generator->getGenerationMethods();
    // Sort generation methods by weight.
    uasort($generation_info, '\Drupal\Component\Utility\SortArray::sortByWeightElement');
    $method_options = array();
    foreach ($generation_info as $method_id => $method) {
      $method_options[$method_id] = '<strong>' . String::checkPlain($method['name']) . '</strong>: ' . String::checkPlain($method['description']);
    }

    $form['method_id'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Generation method'),
      // Set the lowest-weight method as default.
      '#default_value' => key($method_options),
      '#options' => $method_options,
      '#description' => $this->t('Select a package generation method.'),
    );

    $form['description'] = array(
      '#markup' => '<p>' . $this->t('Use the export button below to generate your packaged configuration modules.') . '</p>',
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Export'),
    );
    return $form;
  }

  /**
   * Adds a pseudo-package to display unpackaged configuration.
   */
  protected function addUnpackaged(array &$packages, array $config_collection) {
    $packages['unpackaged'] = array(
      'machine_name' => 'unpackaged',
      'name' => $this->t('Unpackaged'),
      'description' => $this->t('Configuration that has not been added to any package.'),
      'config' => array(),
    );
    foreach ($config_collection as $item_name => $item) {
      if (empty($item['package'])) {
        $packages['unpackaged']['config'][] = $item_name;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $profile_settings = $form_state->getValue('profile');
    \Drupal::configFactory()->getEditable('features.settings')
      ->set('profile', $profile_settings)
      ->save();

    $this->assigner->assignConfigPackages();

    $method_id = $form_state->getValue('method_id');
    if ($profile_settings['add']) {
      $this->generator->generateProfile($method_id);
    }
    else {
      $this->generator->generatePackages($method_id);
    }

    $this->generator->applyExportFormSubmit($method_id, $form, $form_state);
  }

}
