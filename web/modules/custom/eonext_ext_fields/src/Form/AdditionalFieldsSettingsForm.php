<?php

namespace Drupal\eonext_ext_fields\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Additional fields settings form class.
 */
class AdditionalFieldsSettingsForm extends ConfigFormBase {

  public const FORM_ID = 'eonext_ext_fields.settings_form';

  public const CONFIG_ID = 'eonext_ext_fields.settings';

  public const FBI_FIELD_PATTERN = '[a-z]+(\.[a-z]+)*';

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      self::CONFIG_ID,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return self::FORM_ID;
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['additional_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional fields to expose inside Works object'),
      '#description' => $this->t('One entry per line. <br />LABEL|DOT.SEPARATED_PATH, e.g.: TvTitle|titles.tvSeries.title'),
      '#config_target' => self::CONFIG_ID . ':' . 'additional_fields',
    ];

    $form['cover_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User covers from this path within Works object'),
      '#description' => $this->t('Fetch alternative covers from FBI well field, .e.g: "manifestations.latest.cover.detail"'),
      '#config_target' => self::CONFIG_ID . ':' . 'cover_override',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $additional_fields = $form_state->getValue('additional_fields');
    $additional_fields_parsed = [];
    foreach (array_filter(preg_split("/\t|\r|\n/", $additional_fields)) as $additional_field_entry) {
      $additional_field_entry = trim($additional_field_entry);
      if (!preg_match('/^[a-z0-9\s]+\|' . self::FBI_FIELD_PATTERN . '$/i', $additional_field_entry)) {
        $form_state->setErrorByName('additional_fields', $this->t('Failed to validate one or more entries.'));
        break;
      }

      $additional_fields_parsed[] = $additional_field_entry;
    }

    $form_state->setValue('additional_fields', implode("\n", $additional_fields_parsed));

    $cover_override = trim($form_state->getValue('cover_override'));
    if (!empty($cover_override) && !preg_match('/^' . self::FBI_FIELD_PATTERN . '$/i', $cover_override)) {
      $form_state->setErrorByName('cover_override', $this->t('Failed to validate pattern.'));
    }
    $form_state->setValue('cover_override', $cover_override);
  }

}
