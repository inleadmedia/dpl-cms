<?php

declare(strict_types=1);

namespace Drupal\eonext_branch_opening_hours\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Branch Opening Hours module.
 */
final class BranchOpeningHoursSettingsForm extends ConfigFormBase {

  /**
   * Constructs a BranchOpeningHoursSettingsForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUsageInterface $fileUsage,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('file.usage'),
    );
  }

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

    $logo_fid = $config->get('logo');
    $form['logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Opening hours layout logo'),
      '#description' => $this->t('Optional logo shown on the bare opening hours page. Allowed types: PNG, JPG, SVG.'),
      '#upload_location' => 'public://eonext_branch_opening_hours',
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'png jpg jpeg svg',
        ],
      ],
      '#default_value' => $logo_fid ? [$logo_fid] : NULL,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $fids = $form_state->getValue('logo');
    if (empty($fids)) {
      return;
    }
    $fid = reset($fids);
    if (!$fid) {
      return;
    }
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file instanceof FileInterface) {
      $form_state->setErrorByName('logo', $this->t('The logo file could not be loaded.'));
      return;
    }
    if (!_eonext_branch_opening_hours_file_uri_exists($file)) {
      $form_state->setErrorByName('logo', $this->t('The logo file is missing from disk.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->getEditable('eonext_branch_opening_hours.settings');
    $old_fid = $config->get('logo');
    $logo_fids = $form_state->getValue('logo') ?? [];
    $new_fid = !empty($logo_fids) ? (int) reset($logo_fids) : NULL;

    if ($old_fid && (int) $old_fid !== (int) ($new_fid ?? 0)) {
      $old_file = $this->entityTypeManager->getStorage('file')->load($old_fid);
      if ($old_file instanceof FileInterface) {
        $this->fileUsage->delete($old_file, 'eonext_branch_opening_hours', 'settings', 0);
      }
    }

    if ($new_fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($new_fid);
      if ($file instanceof FileInterface) {
        $file->setPermanent();
        $file->save();
        if ((int) $new_fid !== (int) $old_fid) {
          $this->fileUsage->add($file, 'eonext_branch_opening_hours', 'settings', 0);
        }
      }
    }

    $config
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('domain', $form_state->getValue('domain'))
      ->set('consumer_hash', $form_state->getValue('consumer_hash'));
    if ($new_fid) {
      $config->set('logo', $new_fid);
    }
    else {
      $config->clear('logo');
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
