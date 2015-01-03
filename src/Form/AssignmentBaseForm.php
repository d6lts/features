<?php

/**
 * @file
 * Contains \Drupal\config_packager\Form\AssignmentBaseForm.
 */

namespace Drupal\config_packager\Form;

use Drupal\config_packager\Form\AssignmentFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the selected configuration assignment method for this site.
 */
class AssignmentBaseForm extends AssignmentFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'config_packager_assignment_base_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $defaults = $this->configFactory->get('config_packager.assignment')->get('base.types');
    $this->setTypeSelect($form, $defaults, $this->t('base'));
    $this->setActions($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = array_filter($form_state->getValue('types'));

    $this->configFactory->get('config_packager.assignment')->set('base.types', $types)->save();

    $form_state->setRedirect('config_packager.assignment');
    drupal_set_message($this->t('Package assignment configuration saved.'));
  }

}
