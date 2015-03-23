<?php

/**
 * @file
 * Contains \Drupal\features_ui\Form\FeaturesExportForm.
 */

namespace Drupal\features_ui\Form;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\features\FeaturesAssignerInterface;
use Drupal\features\FeaturesGeneratorInterface;
use Drupal\features\FeaturesManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
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

    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#name'] == 'package_set') {
      $package_set = $form_state->getValue('package_set', '');
      if ($package_set == '_') {
        $package_set = '';
      }
      $this->featuresManager->applyNamespace($package_set);
    }
    else {
      $this->assigner->assignConfigPackages();
    }
    $packages = $this->featuresManager->getPackages();

    $config_collection = $this->featuresManager->getConfigCollection();
    // Add in unpackaged configuration items.
    $this->addUnpackaged($packages, $config_collection);
    $packages = $this->featuresManager->filterPackages($packages);

    $profile = $this->featuresManager->getProfile();
    $package_sets = $this->featuresManager->getPackageSets();

    $current_set = !empty($profile['machine_name']) ? $profile['machine_name'] : '_';
    $options = array(
      '_' => t('--All--'),
    );
    foreach ($package_sets as $name => $set) {
      $options[$name] = $set['name'];
    }
    $form['#prefix'] = '<div id="edit-features-wrapper">';
    $form['#suffix'] = '</div>';
    $form['package_set'] = array(
      '#title' => t('Package Set'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $current_set,
      '#prefix' => '<div id="edit-package-set-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => array(
        'callback' => '::updatePreview',
        'wrapper' => 'edit-features-preview-wrapper',
      ),
      '#attributes' => array(
        'data-new-package-set' => 'status',
      ),
    );

    $form['preview'] = $this->buildListing($packages);

    $form['#attached'] = array(
      'library' => array(
        'features_ui/drupal.features_ui.admin',
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
   * Handles switching the configuration type selector.
   */
  public function updatePreview($form, FormStateInterface $form_state) {
    return $form['preview'];
  }

  /**
   * Build the portion of the form showing a listing of features.
   * @param array $packages
   * @return array render array of form element
   */
  protected function buildListing($packages) {

    $header = array(
      'name' => array('data' => $this->t('Feature')),
      'machine_name' => array('data' => $this->t('')),
      'details' => array('data' => $this->t('Description'), 'class' => array(RESPONSIVE_PRIORITY_LOW)),
      'version' => array('data' => $this->t('Version'), 'class' => array(RESPONSIVE_PRIORITY_LOW)),
      'status' => array('data' => $this->t('Status'), 'class' => array(RESPONSIVE_PRIORITY_LOW)),
      'state' => array('data' => $this->t('State'), 'class' => array(RESPONSIVE_PRIORITY_LOW)),
    );

    $options = array();
    foreach ($packages as $package) {
      $options[$package['machine_name']] = $this->buildPackageDetail($package);
    }

    $element = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#attributes' => array('class' => array('features-listing')),
      '#prefix' => '<div id="edit-features-preview-wrapper">',
      '#suffix' => '</div>',
    );

    return $element;
  }

  /**
   * Build the details of a package.
   * @param array $package
   * @return array render array of form element
   */
  protected function buildPackageDetail($package) {
    $config_collection = $this->featuresManager->getConfigCollection();

    $element['name'] = array('data' => String::checkPlain($package['name']), 'class' => array('feature-name'));
    $element['machine_name'] = $package['machine_name'];
    $element['status'] = $this->featuresManager->statusLabel($package['status']);
    // Use 'data' instead of plain string value so a blank version doesn't remove column from table.
    $element['version'] = array('data' => String::checkPlain($package['version']));
    $overrides = $this->featuresManager->detectOverrides($package);
    if (!empty($overrides) && ($package['status'] != FeaturesManagerInterface::STATUS_NO_EXPORT)) {
      $element['state'] = $this->featuresManager->stateLabel(FeaturesManagerInterface::STATE_OVERRIDDEN);
    }
    else {
      $element['state'] = '';
    }

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
    $package_config['dependencies'] = array();
    if (!empty($package['dependencies'])) {
      foreach ($package['dependencies'] as $dependency) {
        $package_config['dependencies'][] = array(
          'name' => $dependency,
          'label' => $this->moduleHandler->getName($dependency),
        );
      }
    }

    $config_types = $this->featuresManager->listConfigTypes();
    // Add dependencies.
    $config_types['dependencies'] = $this->t('Dependencies');
    uasort($config_types, 'strnatcasecmp');

    $rows = array();
    // Use sorted array for order.
    foreach ($config_types as $type => $label) {
      // For each component type, offer alternating rows.
      $row = array();
      if (isset($package_config[$type])) {
        $row[] = array(
          'data' => array(
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => String::checkPlain($label),
            '#attributes' => array(
              'title' => String::checkPlain($type),
              'class' => 'features-item-label',
            ),
          ),
        );
        $row[] = array(
          'data' => array(
            '#theme' => 'features_items',
            '#items' => $package_config[$type],
            '#value' => String::checkPlain($label),
            '#title' => String::checkPlain($type),
          ),
          'class' => 'item',
        );
      }
      $rows[] = $row;
    }
    $element['details'] = array(
      '#type' => 'table',
      '#rows' => $rows,
    );

    $details = array(
      '#type' => 'details',
      '#title' => XSS::filterAdmin($package['description']),
      '#description' => array('data' => $element['details']),
    );
    $element['details'] = array('class' => array('description', 'expand'), 'data' => $details);

    return $element;
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
    $profile_settings = \Drupal::config('features.settings')->get('profile');

    $this->assigner->assignConfigPackages();

    $packages = array_filter($form_state->getValue('preview'));
    $method_id = $form_state->getValue('method_id');

    if ($profile_settings['add']) {
      $this->generator->generateProfile($method_id, $packages, FALSE);
    }
    else {
      $this->generator->generatePackages($method_id, $packages, FALSE);
    }

    $this->generator->applyExportFormSubmit($method_id, $form, $form_state);
  }

}
