<?php

/**
 * @file
 * Contains \Drupal\config_packager\Form\ConfigPackagerExportForm.
 */

namespace Drupal\config_packager\Form;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\config_packager\ConfigPackagerAssignerInterface;
use Drupal\config_packager\ConfigPackagerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the configuration export form.
 */
class ConfigPackagerExportForm extends FormBase {

  /**
   * The package assigner.
   *
   * @var array
   */
  protected $assigner;

  /**
   * The configuration packager manager.
   *
   * @var array
   */
  protected $configPackagerManager;

  /**
   * Constructs a ConfigPackagerExportForm object.
   *
   * @param \Drupal\Core\Config\StorageInterface $target_storage
   *   The target storage.
   */
  public function __construct(ConfigPackagerManagerInterface $config_packager_manager, ConfigPackagerAssignerInterface $assigner) {
    $this->configPackagerManager = $config_packager_manager;
    $this->assigner = $assigner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config_packager.manager'),
      $container->get('config_packager_assigner')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_packager_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->assigner->assignConfigPackages();
    $packages = $this->configPackagerManager->getPackages();
    $config_collection = $this->configPackagerManager->getConfigCollection();
    $config_types = $this->configPackagerManager->getConfigTypes();
    $packager_config = \Drupal::config('config_packager.settings');

    $form['profile_name'] = array(
      '#title' => $this->t('Profile name'),
      '#type' => 'textfield',
      '#default_value' => $packager_config->get('profile.name'),
      '#description' => $this->t('The human-readable name of an install profile or distribution'),
      '#required' => TRUE,
      '#size' => 30,
    );

    $form['profile_machine_name'] = array(
      '#type' => 'machine_name',
      '#maxlength' => 64,
      '#machine_name' => array(
        'source' => array('profile_name'),
      ),
      '#default_value' => $packager_config->get('profile.machine_name'),
      '#description' => $this->t('A unique machine-readable name for the install profile or distribution. It must only contain lowercase letters, numbers, and underscores.'),
    );

    $form['profile_description'] = array(
      '#title' => $this->t('Distribution description'),
      '#type' => 'textfield',
      '#default_value' => $packager_config->get('profile.description'),
      '#description' => $this->t('A description of your install profile or distribution.'),
      '#size' => 30,
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
      $form['preview'][$package['machine_name']] = array(
        '#type' => 'container',
        '#attributes' => array('class' => array('config-packager-items-wrapper')),
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
              '#theme' => 'config_packager_items',
              '#items' => $package_config[$type],
            ),
            'class' => 'item',
          );
        }
      }
      $form['preview'][$package['machine_name']]['items'] = array(
        '#type' => 'table',
        '#header' => array($this->t('@name: !description', array('@name' => $package['name'], '!description' => XSS::filterAdmin($package['description'])))),
        '#attributes' => array('class' => array('config-packager-items')),
        '#rows' => $rows,
      );
    }
    $form['#attached']['css'][] = drupal_get_path('module', 'config_packager') . '/css/config_packager.admin.css';

    $form['description'] = array(
      '#markup' => '<p>' . $this->t('Use the export button below to download your packaged configuration modules.') . '</p>',
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Export'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    \Drupal::config('config_packager.settings')
      ->set('profile.machine_name', $form_state->getValue('profile_machine_name'))
      ->set('profile.name', $form_state->getValue('profile_name'))
      ->set('profile.description', $form_state->getValue('profile_description'))
      ->save();

    $this->assigner->assignConfigPackages();

    $this->configPackagerManager->generate();

    // Redirect to the archive file download.
    $form_state->setRedirect('config_packager.export_download');
  }

}
