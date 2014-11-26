<?php

/**
 * @file
 * Contains \Drupal\config_packager\Form\AssignmentCoreForm.
 */

namespace Drupal\config_packager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\config_packager\Form\AssignmentFormBase;

/**
 * Configure the selected configuration assignment method for this site.
 */
class AssignmentCoreForm extends AssignmentFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'config_packager_assignment_core_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $defaults = $this->configFactory->get('config_packager.assignment')->get('core.types');
    $this->setTypeSelect($form, $defaults);
    $this->setActions($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = $form_state->getValue('types');

    $this->configFactory->get('config_packager.assignment')->set('core.types', $types)->save();

    $form_state->setRedirect('config_packager.assignment');
    drupal_set_message($this->t('Package assignment configuration saved.'));
  }

}
