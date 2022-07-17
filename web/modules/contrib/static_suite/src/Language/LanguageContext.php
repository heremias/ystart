<?php

namespace Drupal\static_suite\Language;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;

/**
 * Simple service to execute a callable in a defined language context.
 *
 * @see GraphQLLanguageContext, based on it.
 */
class LanguageContext implements LanguageContextInterface {

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Externally overridable language negotiator.
   *
   * @var \Drupal\static_suite\Language\OverridableLanguageNegotiatorInterface
   */
  protected $languageNegotiator;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * GraphQLLanguageContext constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\static_suite\Language\OverridableLanguageNegotiatorInterface $languageNegotiator
   *   Externally overridable language negotiator.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user.
   */
  public function __construct(LanguageManagerInterface $languageManager, OverridableLanguageNegotiatorInterface $languageNegotiator, AccountProxyInterface $currentUser) {
    $this->languageManager = $languageManager;
    $this->languageNegotiator = $languageNegotiator;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   *
   * @noinspection PhpMissingReturnTypeInspection
   */
  public function executeInLanguageContext(callable $callable, string $langcode) {
    $originalLanguage = $this->languageManager->getCurrentLanguage();
    $originalLangcode = $originalLanguage->getId();
    $newLanguage = $this->languageManager->getLanguage($langcode);
    $originalNegotiator = NULL;
    if ($newLanguage && ($originalLangcode !== $langcode) && $this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      $originalNegotiator = $this->languageManager->getNegotiator();
      $this->languageManager->reset();
      $this->languageNegotiator->setLanguageCode($langcode);
      $this->languageNegotiator->setCurrentUser($this->currentUser);
      $this->languageManager->setNegotiator($this->languageNegotiator);
      $this->languageManager->setConfigOverrideLanguage($newLanguage);
    }

    // Execute the callable.
    try {
      return $callable();
    }
    finally {
      // In any case, set the language back to its previous state.
      if (($originalLangcode !== $langcode) && $this->languageManager instanceof ConfigurableLanguageManagerInterface) {
        $this->languageManager->reset();
        if ($originalNegotiator) {
          $this->languageManager->setNegotiator($originalNegotiator);
          $this->languageManager->setConfigOverrideLanguage($originalLanguage);
        }
      }
    }
  }

}
