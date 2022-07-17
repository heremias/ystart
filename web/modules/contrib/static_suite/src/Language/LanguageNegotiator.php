<?php

namespace Drupal\static_suite\Language;

use Drupal\language\LanguageNegotiator as LanguageNegotiatorBase;

/**
 * An externally configurable language negotiator.
 */
class LanguageNegotiator extends LanguageNegotiatorBase implements OverridableLanguageNegotiatorInterface {

  /**
   * The negotiator's language code.
   *
   * @var string
   */
  protected $languageCode;

  /**
   * {@inheritdoc}
   */
  public function initializeType($type) {
    $availableLanguages = $this->languageManager->getLanguages();
    if ($this->languageCode && isset($availableLanguages[$this->languageCode])) {
      $language = $availableLanguages[$this->languageCode];
    }
    else {
      // If no other language was found use the default one.
      $language = $this->languageManager->getDefaultLanguage();
    }

    return [self::METHOD_ID => $language];
  }

  /**
   * Set language code.
   *
   * @param string $languageCode
   */
  public function setLanguageCode(string $languageCode): void {
    $this->languageCode = $languageCode;
  }

}
