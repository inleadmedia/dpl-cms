<?php

namespace Drupal\eonext_translation;

/**
 * Interface for translation service..
 */
interface TranslationServiceInterface {

  /**
   * Get the footer state.
   *
   * @return array
   *   The footer state settings.
   */
  public function getFooterState(): array;

  /**
   * Set the footer settings.
   */
  public function saveFooterToConfig(): void;

  /**
   * Get the footer settings.
   *
   * @return array
   *   The footer settings.
   */
  public function getFooterSettings(): array;

  /**
   * Get the language links.
   *
   * @return array
   *   The language links.
   */
  public function getLanguageLinks(): array;

}
