<?php

/**
 * @file
 * Contains \Drupal\features_ui\Form\FeaturesDiffForm.
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
use Drupal\features\FeaturesInstallStorage;
use Drupal\Component\Diff\DiffFormatter;
use Drupal\config_update\ConfigReverter;

/**
 * Defines the features differences form.
 */
class FeaturesDiffForm extends FormBase {

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
   * The target storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The config differ.
   *
   * @var \Drupal\config_update\ConfigDiffInterface
   */
  protected $configDiff;

  /**
   * The diff formatter.
   *
   * @var \Drupal\Core\Diff\DiffFormatter
   */
  protected $diffFormatter;

  /**
   * The extension storage
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $extension_storage;

  /**
   * Constructs a FeaturesDiffForm object.
   *
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *   The features manager.
   */
  public function __construct(FeaturesManagerInterface $features_manager, FeaturesAssignerInterface $assigner,
                              StorageInterface $config_storage, ConfigDiffInterface $config_diff, DiffFormatter $diff_formatter) {
    $this->featuresManager = $features_manager;
    $this->assigner = $assigner;
    $this->configStorage = $config_storage;
    $this->configDiff = $config_diff;
    $this->diffFormatter = $diff_formatter;
    $this->diffFormatter->show_header = FALSE;
    $this->extension_storage = new FeaturesInstallStorage($this->configStorage);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('features.manager'),
      $container->get('features_assigner'),
      $container->get('config.storage'),
      $container->get('config_update.config_diff'),
      $container->get('diff.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'features_diff_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $featurename = '') {
    $this->assigner->assignConfigPackages();
    $packages = $this->featuresManager->getPackages();
    $form = array();

    if (!empty($featurename) && empty($packages[$featurename])) {
      drupal_set_message(t('Feature !name does not exist.', array('!name' => $featurename)), 'error');
      return array();
    }
    elseif (!empty($featurename)) {
      $packages = array($packages[$featurename]);
    }

    $form['diff'] = array();
    foreach ($packages as $package_name => $package) {
      if ($package['status'] != FeaturesManagerInterface::STATUS_NO_EXPORT) {
        $overrides = $this->featuresManager->detectOverrides($package);
        if (!empty($overrides)) {
          $form['diff'][$package_name]['title'] = array(
            '#markup' => String::checkPlain($package['name']),
            '#prefix' => '<h2>',
            '#suffix' => '</h2>',
          );
          $form['diff'][$package_name]['diffs'] = $this->diffOutput($overrides);
        }
      }
    }

    if (empty($form['diff'])) {
      drupal_set_message(t('No differences exist in exported features.'));
    }

    $form['#attached']['library'][] = 'system/diff';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  protected function diffOutput($overrides) {
    $rows = array();
    foreach ($overrides as $name) {
      $rows[] = array(array('data' => $name, 'colspan' => 4, 'header' => TRUE));

      $active = $this->configStorage->read($name);
      $extension = $this->extension_storage->read($name);
      if (empty($extension)) {
        $extension = array();
      }
      $diff = $this->configDiff->diff($extension, $active);
      $rows += $this->diffFormatter->format($diff);
    }

    $header = array(
      array('data' => '', 'class' => 'diff-marker'),
      array('data' => t('Active site config'), 'class' => 'diff-context'),
      array('data' => '', 'class' => 'diff-marker'),
      array('data' => t('Feature code config'), 'class' => 'diff-context'),
    );

    $output = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => array('class' => array('diff', 'features-diff')),
    );

    return $output;
  }

}
