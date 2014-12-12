<?php

/**
 * @file
 * Contains \Drupal\config_packager\Form\AssignmentExcludeForm.
 */

namespace Drupal\config_packager\Form;

use Drupal\config_packager\Form\AssignmentFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure the selected configuration assignment method for this site.
 */
class AssignmentExcludeForm extends AssignmentFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'config_packager_assignment_exclude_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $settings = $this->configFactory->get('config_packager.assignment');
    $this->setTypeSelect($form, $settings->get('exclude.types'), $this->t('exclude'));
    $form['packaged'] = array(
      '#type' => 'checkbox',
      '#title' => t('Exclude module-provided configuration'),
      '#default_value' => $settings->get('exclude.packaged'),
      '#description' => $this->t('Select this option to exclude from packaging any configuration that is provided by already enabled modules.'),
    );

    $this->setActions($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = array_filter($form_state->getValue('types'));
    $packaged = $form_state->getValue('packaged');

    $this->configFactory->get('config_packager.assignment')
      ->set('exclude.packaged', $packaged)
      ->set('exclude.types', $types)
      ->save();

    $form_state->setRedirect('config_packager.assignment');
    drupal_set_message($this->t('Package assignment configuration saved.'));
  }

}
