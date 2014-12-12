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

    $module_settings = $settings->get('exclude.module');

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

    $machine_name = $this->configFactory->get('config_packager.settings')->get('profile.machine_name');
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
    $exclude_module = $form_state->getValue('module');

    $this->configFactory->get('config_packager.assignment')
      ->set('exclude.module', $exclude_module)
      ->set('exclude.types', $types)
      ->save();

    $form_state->setRedirect('config_packager.assignment');
    drupal_set_message($this->t('Package assignment configuration saved.'));
  }

}
