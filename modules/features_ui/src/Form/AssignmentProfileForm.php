<?php

/**
 * @file
 * Contains \Drupal\features_ui\Form\AssignmentProfileForm.
 */

namespace Drupal\features_ui\Form;

use Drupal\features_ui\Form\AssignmentFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the selected configuration assignment method for this profile.
 */
class AssignmentProfileForm extends AssignmentFormBase {

  const METHOD_ID = 'profile';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'features_assignment_profile_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $bundle_name = NULL) {
    $this->currentBundle = $this->assigner->loadBundle($bundle_name);
    $settings = $this->currentBundle->getAssignmentSettings(self::METHOD_ID);

    $this->setConfigTypeSelect($form, $settings['types']['config'], $this->t('profile'));

    $form['theme'] = array(
      '#type' => 'checkbox',
      '#title' => t('Include settings for the default and administration themes'),
      '#default_value' => $settings['theme'],
      '#description' => $this->t('Select this option to add settings from the default and administration themes.'),
    );

    $standard_settings = $settings['standard'];

    $form['standard'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Crib from the Standard profile'),
      '#tree' => TRUE,
    );
    $form['standard']['files'] = array(
      '#type' => 'checkbox',
      '#title' => t('Crib code'),
      '#default_value' => $standard_settings['files'],
      '#description' => $this->t('Select this option to add configuration and other files to the optional install profile from the Drupal core Standard install profile. Without these additions, a generated install profile will be missing some important initial setup.'),
    );
    $form['standard']['dependencies'] = array(
      '#type' => 'checkbox',
      '#title' => t('Crib dependencies'),
      '#default_value' => $standard_settings['dependencies'],
      '#description' => $this->t('Select this option to add module and theme dependencies from the Standard install profile.'),
    );

    $this->setActions($form);

    return $form;
  }

 /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->setValue('types', array_map('array_filter', $form_state->getValue('types')));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = array(
      'theme' => $form_state->getValue('theme'),
      'standard' => $form_state->getValue('standard'),
      'types' => $form_state->getValue('types'),
    );

    $this->currentBundle->setAssignmentSettings(self::METHOD_ID, $settings)->save();
    $this->setRedirect($form_state);

    drupal_set_message($this->t('Package assignment configuration saved.'));
  }

}
