<?php

namespace Drupal\openy_session_cleaner\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CleanerSettingsForm.
 *
 * @package Drupal\openy_session_cleaner\Form
 */
class CleanerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'openy_session_cleaner.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openy_session_cleaner_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('activenet_wrapper.settings');

    $form['limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Items per cron run'),
      '#description' => $this->t('The number of outdated sessions and classes to remove per cron execution'),
      '#required' => TRUE,
      '#default_value' => ($config->get('limit')) ? $config->get('limit') : '',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('openy_session_cleaner.settings');
    $config->set('limit', $form_state->getValue('limit'))->save();

    parent::submitForm($form, $form_state);
  }

}
