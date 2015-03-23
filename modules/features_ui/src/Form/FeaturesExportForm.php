<?php

/**
 * @file
 * Contains \Drupal\features_ui\Form\FeaturesExportForm.
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

    $show_if_new_set_selected = array(
      'visible' => array(
        ':input[data-new-package-set="status"]' => array('value' => '_new_'),
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
    $element = array(
      '#tree' => TRUE,
      '#theme' => 'features_listing',
      '#header' => array(
        array('data' => ''),
        array('data' => $this->t('Feature'), 'class' => array('name')),
        array('data' => ''),
        array('data' => $this->t('Description'), 'class' => array('description', RESPONSIVE_PRIORITY_LOW)),
        array('data' => $this->t('Version'), 'class' => array('version', RESPONSIVE_PRIORITY_LOW)),
        array('data' => $this->t('State'), 'class' => array('state', RESPONSIVE_PRIORITY_LOW)),
      ),
      '#attributes' => array('class' => array('features-listing')),
    );
    foreach ($packages as $package) {
      $element[$package['machine_name']] = $this->buildPackageDetail($package);
    }

    return $element;
  }

  /**
   * Build the details of a package.
   * @param array $package
   * @return array render array of form element
   */
  protected function buildPackageDetail($package) {
    $config_collection = $this->featuresManager->getConfigCollection();

    $element['name']['#markup'] = $package['name'];
    $element['machine_name']['#markup'] = $package['machine_name'];
    $element['description']['#markup'] = $package['description'];
    $element['status']['#markup'] = $this->featuresManager->statusLabel($package['status']);
    $element['version']['#markup'] = $package['version'];
    $element['state']['#markup'] = ($package['state'] != FeaturesManagerInterface::STATE_DEFAULT) ? $this->featuresManager->stateLabel($package['state']) : '';

    // Present a checkbox indicating the status of a module.
    // Only enable the checkbox if not-exported so it can be selected for export.
    $element['enable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Install'),
      '#default_value' => ($package['status'] == FeaturesManagerInterface::STATUS_ENABLED),
      '#disabled' => ($package['status'] != FeaturesManagerInterface::STATUS_NO_EXPORT),
    );

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
      '#attributes' => array('class' => array('config-packager-items')),
      '#rows' => $rows,
    );
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
