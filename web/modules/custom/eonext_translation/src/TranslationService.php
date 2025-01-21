<?php

namespace Drupal\eonext_translation;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Translation service.
 */
class TranslationService implements TranslationServiceInterface {


  /**
   * Footer config settings.
   *
   * @var string
   */
  const FOOTER_SETTINGS = 'eonext_translation.footer';

  /**
   * The footer state key.
   *
   * @var string
   */
  const FOOTER_SETTINGS_STATE = 'dpl_footer_values';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new TranslationService object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(StateInterface $state, ConfigFactoryInterface $config_factory) {
    $this->state = $state;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFooterState(): array {
    return $this->state->get(static::FOOTER_SETTINGS_STATE, []);
  }

  /**
   * {@inheritdoc}
   */
  public function saveFooterToConfig(): void {

    $footerState = $this->getFooterState();

    // Save the footer settings.
    $config = $this->configFactory->getEditable(static::FOOTER_SETTINGS);
    $config
      ->set('footer_items', $footerState['footer_items'])
      ->set('secondary_links', $footerState['secondary_links'])
      ->set('facebook', $footerState['facebook'])
      ->set('instagram', $footerState['instagram'])
      ->set('youtube', $footerState['youtube'])
      ->set('spotify', $footerState['spotify'])
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getFootersettings(): array {
    $config = $this->configFactory->get(static::FOOTER_SETTINGS) ?: [];
    $data = $config->get();

    // Remove the _core and langcode key.
    unset($data['_core']);
    unset($data['langcode']);

    return $data;
  }

}
