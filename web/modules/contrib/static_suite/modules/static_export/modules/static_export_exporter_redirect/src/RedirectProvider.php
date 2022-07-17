<?php

namespace Drupal\static_export_exporter_redirect;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\path_alias\AliasRepositoryInterface;

/**
 * A provider of redirects, ready to be consumed by the Redirect Exporter.
 */
class RedirectProvider implements RedirectProviderInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The path alias repository.
   *
   * @var \Drupal\path_alias\AliasRepositoryInterface
   */
  protected AliasRepositoryInterface $pathAliasRepository;

  /**
   * The redirect repository from Static Export redirect exporter.
   *
   * @var \Drupal\static_export_exporter_redirect\RedirectRepositoryInterface
   */
  protected RedirectRepositoryInterface $redirectRepository;

  /**
   * Array of path prefixes from language negotiation.
   *
   * @var array
   */
  protected array $pathPrefixes = [];

  /**
   * Array of preloaded path aliases by language.
   *
   * @var array
   */
  protected array $preloadedPathAliases = [];

  /**
   * Constructs a RedirectRepository object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\path_alias\AliasRepositoryInterface $pathAliasRepository
   *   The path alias repository.
   * @param \Drupal\static_export_exporter_redirect\RedirectRepositoryInterface $redirectRepository
   *   The redirect repository from Static Export redirect exporter.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LanguageManagerInterface $languageManager, AliasRepositoryInterface $pathAliasRepository, RedirectRepositoryInterface $redirectRepository) {
    $this->configFactory = $configFactory;
    $this->languageManager = $languageManager;
    $this->pathAliasRepository = $pathAliasRepository;
    $this->redirectRepository = $redirectRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllRules(): array {
    $redirects = $this->redirectRepository->findAll();

    // Get configuration about path prefixes.
    $languageNegotiationConfig = $this->configFactory->get('language.negotiation')
      ->get('url');
    $enabledLanguageDetectionMethods = $this->configFactory->get('language.types')
      ->get('negotiation.language_interface.enabled');
    $isLanguageUrlDetectionMethodEnabled = is_array($enabledLanguageDetectionMethods) && array_key_exists('language-url', $enabledLanguageDetectionMethods);
    $isPathPrefixed = $isLanguageUrlDetectionMethodEnabled && $languageNegotiationConfig['source'] === 'path_prefix';
    $this->pathPrefixes = $languageNegotiationConfig['prefixes'];

    // Get all languages in advance to optimize performance.
    $languages = $this->languageManager->getLanguages();
    $undLanguage = $this->languageManager->getLanguage(LanguageInterface::LANGCODE_NOT_SPECIFIED);

    // Normalize URLs.
    $allRedirects = [];
    foreach ($redirects as $redirect) {
      $redirect['redirect_source__path_original'] = $redirect['redirect_source__path'];
      $redirect['redirect_source__path'] = '/' . $redirect['redirect_source__path'];
      $redirect['redirect_redirect__uri_original'] = $redirect['redirect_redirect__uri'];
      // All redirects should have a language ("und" at least), but let's check
      // it anyway.
      if (isset($redirect['language'])) {
        if ($isPathPrefixed) {
          // When a redirect language is "und", it means it's available for all
          // languages, with and without language prefix.
          if ($redirect['language'] === LanguageInterface::LANGCODE_NOT_SPECIFIED && $undLanguage) {
            // Add the redirect without any language prefix.
            $allRedirects[] = $this->getRedirectByLanguage($redirect, $undLanguage);
            // Then, add a redirect for each language, which usually came with a
            // language prefix (if not, duplicated redirects are filtered out
            // later).
            foreach ($languages as $language) {
              $allRedirects[] = $this->getRedirectByLanguage($redirect, $language);
            }
          }
          else {
            // When a redirect language is not "und", it means it's available only
            // for that language.
            $language = $languages[$redirect['language']] ?? NULL;
            if ($language) {
              $allRedirects[] = $this->getRedirectByLanguage($redirect, $language);
            }
          }
        }
        else {
          $redirect['redirect_source__path'] = $this->urlencode($redirect['redirect_source__path']);
          $language = $languages[$redirect['language']] ?? NULL;
          if ($language) {
            $allRedirects[] = $this->getRedirectByLanguage($redirect, $language, FALSE);
          }
        }
      }
    }

    return $this->filter($allRedirects);
  }

  /**
   * Get a processed redirect by language.
   *
   * It normalizes redirect_source__path and redirect_redirect__uri accordingly
   * to the given language.
   *
   * @param array $redirect
   *   Array with all redirect data.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   A language object.
   * @param bool $addPrefix
   *   A flag to indicate that the language prefix should be added to
   *   redirect_source__path and redirect_redirect__uri.
   *
   * @return array|null
   *   The processed redirect array
   */
  protected function getRedirectByLanguage(array $redirect, LanguageInterface $language, bool $addPrefix = TRUE): ?array {
    $langcode = $language->getId();
    $prefix = $addPrefix && isset($this->pathPrefixes[$langcode]) ? '/' . $this->pathPrefixes[$langcode] : '';
    $from = $prefix . $redirect['redirect_source__path'];

    $to = $redirect['redirect_redirect__uri'];
    if (str_starts_with($to, 'internal:/') || str_starts_with($to, 'entity:')) {
      // Remove "internal:" and "entity:" ensuring both get a leading slash.
      // They use a different format, with and without leading slash:
      // - internal:/node/12345
      // - entity:node/12345.
      $to = str_replace(['internal:/', 'entity:'], '/', $to);
      // Preload path aliases per language everytime a new language is used.
      if (!isset($this->preloadedPathAliases[$langcode])) {
        $this->preloadedPathAliases[$langcode] = $this->pathAliasRepository->preloadPathAlias([], $langcode);
      }
      // Instead of using Url::fromUri(), which goes through all inbound
      // processing (a performance bottleneck), manually add the language prefix
      // to the alias.
      if (isset($this->preloadedPathAliases[$langcode][$to])) {
        $to = $prefix . $this->preloadedPathAliases[$langcode][$to];
      }
    }
    if ($from === $to) {
      return NULL;
    }

    $redirect['redirect_source__path'] = $this->urlencode($from);
    $redirect['redirect_redirect__uri'] = $this->urlencode($to);

    if ($redirect['language'] !== $langcode) {
      $redirect['language_original'] = $redirect['language'];
      $redirect['language'] = $langcode;
    }

    return $redirect;
  }

  /**
   * Filters out invalid redirects.
   *
   * Removes all redirects which contain the same from and to, duplicated ones,
   * and those containing "/node/".
   *
   * @param array $redirects
   *   Array of redirects.
   *
   * @return array
   *   Array of valid redirects.
   */
  protected function filter(array $redirects): array {
    $validRedirects = [];
    foreach ($redirects as $redirect) {
      if (
        $redirect &&
        $redirect['redirect_source__path'] !== $redirect['redirect_redirect__uri'] &&
        !str_contains($redirect['redirect_redirect__uri'], '/node/')
      ) {
        // Use $redirect['redirect_source__path'] as a key to remove duplicates.
        $validRedirects[$redirect['redirect_source__path']] = $redirect;
      }
    }
    return array_values($validRedirects);
  }

  /**
   * Encodes a URL maintaining slashes.
   *
   * @param string $url
   *   The URL to be encoded.
   *
   * @return string
   *   The encoded URL.
   */
  protected function urlEncode(string $url): string {
    return str_replace("%2F", "/", rawurlencode($url));
  }

}
