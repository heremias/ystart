<?php

namespace Drupal\static_suite\Language;

use Drupal\language\LanguageNegotiatorInterface;

/**
 * An interface for externally configurable language negotiators.
 *
 * This interface defines a setLanguageCode() method that makes possible to
 * manually change the negotiated language without any actual negotiation
 * taking place.
 *
 * It needs an overridden initializeType() method (part of the
 * LanguageNegotiatorInterface) that returns the language set by
 * setLanguageCode().
 */
interface OverridableLanguageNegotiatorInterface extends LanguageNegotiatorInterface {

  /**
   * Set language code.
   *
   * Makes possible to manually change the negotiated language without any
   * actual negotiation taking place.
   *
   * @param string $languageCode
   */
  public function setLanguageCode(string $languageCode): void;

}
