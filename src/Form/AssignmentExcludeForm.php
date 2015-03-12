<?php

/**
 * @file
 * Contains \Drupal\features\Form\AssignmentExcludeForm.
 */

namespace Drupal\features\Form;

use Drupal\features\Form\AssignmentFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the selected configuration assignment method for this site.
 */
class AssignmentExcludeForm extends AssignmentFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'features_assignment_exclude_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $settings = $this->configFactory->get('features.assignment');
    $this->setTypeSelect($form, $settings->get('exclude.types'), $this->t('exclude'));

    $module_settings = $settings->get('exclude.module');

    $form['curated'] = array(
      '#type' => 'checkbox',
      '#title' => t('Exclude designated site-specific configuration'),
      '#default_value' => $settings->get('exclude.curated'),
      '#description' => $this->t('Select this option to exclude from packaging items on a curated list of site-specific configuration.'),
    );

    $form['module'] = array(
      '#type' => 'container',
      '#tree' => TRUE,
    );
    $form['module']['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Exclude module-provided entity configuration'),
      '#default_value' => $module_settings['enabled'],
      '#description' => $this->t('Select this option to exclude from packaging any configuration that is provided by already enabled modules. Note that <a href="!url">simple configuration</a> will not be excluded as it is always module-provided.', array('!url' => 'http://www.drupal.org/node/1809490')),
      '#attributes' => array(
        'data-module-enabled' => 'status',
      ),
    );

    $show_if_module_enabled_checked = array(
      'visible' => array(
        ':input[data-module-enabled="status"]' => array('checked' => TRUE),
      ),
    );

    $info = system_get_info('module', drupal_get_profile());
    $form['module']['profile'] = array(
      '#type' => 'checkbox',
      '#title' => t("Don't exclude install profile's configuration"),
      '#default_value' => $module_settings['profile'],
      '#description' => $this->t("Select this option to not exclude from packaging any configuration that is provided by this site's install profile, %profile.", array('%profile' => $info['name'])),
      '#states' => $show_if_module_enabled_checked,
    );

    $machine_name = $this->configFactory->get('features.settings')->get('profile.machine_name');
    $form['module']['namespace'] = array(
      '#type' => 'checkbox',
      '#title' => t("Don't exclude configuration by namespace"),
      '#default_value' => $module_settings['namespace'],
      '#description' => $this->t("Select this option to not exclude from packaging any configuration that is provided by modules with the package namespace (currently %namespace).", array('%namespace' => $machine_name)),
      '#states' => $show_if_module_enabled_checked,
    );

    $this->setActions($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = array_filter($form_state->getValue('types'));
    $curated = $form_state->getValue('curated');
    $module = $form_state->getValue('module');

    $this->configFactory->getEditable('features.assignment')
      ->set('exclude.types', $types)
      ->set('exclude.curated', $curated)
      ->set('exclude.module', $module)
      ->save();

    $form_state->setRedirect('features.assignment');
    drupal_set_message($this->t('Package assignment configuration saved.'));
  }

}
