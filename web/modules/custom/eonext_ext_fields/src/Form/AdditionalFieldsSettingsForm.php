<?php

namespace Drupal\eonext_ext_fields\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Additional fields settings form class.
 */
class AdditionalFieldsSettingsForm extends ConfigFormBase {

  public const FORM_ID = 'eonext_ext_fields.settings_form';

  public const CONFIG_ID = 'eonext_ext_fields.settings';

  public const FBI_FIELD_PATTERN = '[a-z]+(\.[a-z]+)*';

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs the AdditionalFieldsSettingsForm object.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list service.
   */
  public function __construct(ModuleExtensionList $module_extension_list) {
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.module')
    );
  }

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
    // Get the module path.
    $module_path = '/' . $this->moduleExtensionList->getPath('eonext_ext_fields') . '/help/extended_fields.md';

    $form['additional_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional fields to expose inside Works object'),
      '#description' => $this->t('See the <a href="@url" target="_blank">documentation</a> for the detailed JSON schema.', [
        '@url' => $module_path,
      ]),
      '#rows' => 20,
      '#config_target' => self::CONFIG_ID . ':' . 'additional_fields',
    ];

    $form['cover_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User covers from this path within Works object'),
      '#description' => $this->t('Fetch alternative covers from FBI well field, e.g., "manifestations.latest.cover.detail".'),
      '#config_target' => self::CONFIG_ID . ':' . 'cover_override',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $json_input = $form_state->getValue('additional_fields');
    $trimmed_input = trim(preg_replace('/\s+/', '', $json_input));

    if (json_decode($trimmed_input) === NULL && json_last_error() !== JSON_ERROR_NONE) {
      $form_state->setErrorByName('additional_fields', $this->t('The provided input is not valid JSON.'));
    }

    $cover_override = trim($form_state->getValue('cover_override'));
    if (!empty($cover_override) && !preg_match('/^' . self::FBI_FIELD_PATTERN . '$/i', $cover_override)) {
      $form_state->setErrorByName('cover_override', $this->t('Failed to validate pattern.'));
    }
    $form_state->setValue('cover_override', $cover_override);
  }

}
