<?php

namespace Drupal\eonext_branch_opening_hours\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Branch Opening Hours module.
 */
class BranchOpeningHoursSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_branch_opening_hours_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['eonext_branch_opening_hours.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('eonext_branch_opening_hours.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable opening hours push to external service'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('External domain (without https://)'),
      '#default_value' => $config->get('domain'),
      '#required' => TRUE,
    ];

    $form['consumer_hash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer hash'),
      '#default_value' => $config->get('consumer_hash'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state) + $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('eonext_branch_opening_hours.settings')
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('domain', $form_state->getValue('domain'))
      ->set('consumer_hash', $form_state->getValue('consumer_hash'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
