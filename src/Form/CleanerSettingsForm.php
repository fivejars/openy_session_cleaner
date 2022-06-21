<?php

namespace Drupal\openy_session_cleaner\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Cleaner Settings Form.
 */
class CleanerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'openy_session_cleaner.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openy_session_cleaner_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('openy_session_cleaner.settings');

    $form['limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Items per cron run'),
      '#description' => $this->t('The number of outdated sessions and classes to remove per cron execution'),
      '#required' => TRUE,
      '#default_value' => ($config->get('limit')) ? $config->get('limit') : '',
    ];

    $form['remove_sessions_without_time'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove sessions with empty time'),
      '#description' => $this->t('Removes sessions with empty schedule data'),
      '#default_value' => $config->get('remove_sessions_without_time'),
    ];

    $form['remove_empty_classes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove empty classes'),
      '#description' => $this->t('Removes classes without any sessions'),
      '#default_value' => $config->get('remove_empty_classes'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('openy_session_cleaner.settings');
    $config->set('limit', $form_state->getValue('limit'));
    $config->set('remove_sessions_without_time', $form_state->getValue('remove_sessions_without_time'));
    $config->set('remove_empty_classes', $form_state->getValue('remove_empty_classes'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
