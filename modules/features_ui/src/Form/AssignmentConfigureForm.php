<?php

/**
 * @file
 * Contains \Drupal\features_ui\Form\AssignmentConfigureForm.
 */

namespace Drupal\features_ui\Form;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\features\FeaturesManagerInterface;
use Drupal\features\FeaturesAssignerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures the configuration assignment methods for this site.
 */
class AssignmentConfigureForm extends FormBase {

  /**
   * The features manager.
   *
   * @var \Drupal\features\FeaturesManagerInterface
   */
  protected $featuresManager;

  /**
   * The package assigner.
   *
   * @var \Drupal\features\FeaturesAssignerInterface
   */
  protected $assigner;

  /**
   * Constructs a AssignmentConfigureForm object.
   *
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *   The features manager.
   * @param \Drupal\features\FeaturesAssignerInterface $assigner
   *   The configuration assignment methods manager.
   */
  public function __construct(FeaturesManagerInterface $features_manager, FeaturesAssignerInterface $assigner) {
    $this->featuresManager = $features_manager;
    $this->assigner = $assigner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('features.manager'),
      $container->get('features_assigner')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'features_assignment_configure_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $assignment_info = $this->assigner->getAssignmentMethods();

    $settings = \Drupal::config('features.settings');
    $enabled_methods = $settings->get('assignment.enabled') ?: array();
    $methods_weight = $settings->get('assignment.method_weights') ?: array();

    // Add missing data to the methods lists.
    foreach ($assignment_info as $method_id => $method) {
      if (!isset($methods_weight[$method_id])) {
        $methods_weight[$method_id] = isset($method['weight']) ? $method['weight'] : 0;
      }
    }

    // Order methods list by weight.
    asort($methods_weight);

    $form = array(
      '#tree' => TRUE,
      '#show_operations' => FALSE,
      'weight' => array('#tree' => TRUE),
    );

    foreach ($methods_weight as $method_id => $weight) {

      // A packaging method might no longer be available if the defining module has
      // been disabled after the last configuration saving.
      if (!isset($assignment_info[$method_id])) {
        continue;
      }

      $enabled = isset($enabled_methods[$method_id]);
      $method = $assignment_info[$method_id];

      $method_name = String::checkPlain($method['name']);

      $form['weight'][$method_id] = array(
        '#type' => 'weight',
        '#title' => $this->t('Weight for !title package assignment method', array('!title' => Unicode::strtolower($method_name))),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => array('class' => array('assignment-method-weight')),
        '#delta' => 20,
      );

      $form['title'][$method_id] = array('#markup' => $method_name);

      $form['enabled'][$method_id] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Enable !title package assignment method', array('!title' => Unicode::strtolower($method_name))),
        '#title_display' => 'invisible',
        '#default_value' => $enabled,
      );

      $form['description'][$method_id] = array('#markup' => Xss::filterAdmin($method['description']));

      $config_op = array();
      if (isset($method['config_route_name'])) {
        $config_op['configure'] = array(
          'title' => $this->t('Configure'),
          'url' => Url::fromRoute($method['config_route_name']),
        );
        // If there is at least one operation enabled show the operation
        // column.
        $form['#show_operations'] = TRUE;
      }
      $form['operation'][$method_id] = array(
        '#type' => 'operations',
        '#links' => $config_op,
      );
    }

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

    $enabled_methods = array_filter($form_state->getValue('enabled'));
    ksort($enabled_methods);
    $method_weights = $form_state->getValue('weight');
    ksort($method_weights);

    $settings = \Drupal::configFactory()->getEditable('features.settings');
    $settings->set('assignment.method_weights', $method_weights)->save();
    $this->assigner->saveConfiguration($enabled_methods);

    $form_state->setRedirect('features.assignment');
    drupal_set_message($this->t('Package assignment configuration saved.'));
  }

}
