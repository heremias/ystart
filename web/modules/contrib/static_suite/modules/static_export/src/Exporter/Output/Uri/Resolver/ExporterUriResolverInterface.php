<?php

namespace Drupal\static_export\Exporter\Output\Uri\Resolver;

use Drupal\Core\Language\LanguageInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;

/**
 * Interface for a URI resolver of exported items (entity/config/locale).
 *
 * Custom exporters implement their own logic. Hence, they are not supported by
 * URI resolvers.
 */
interface ExporterUriResolverInterface {

  /**
   * Get URIs from a exported item (entity/config/locale).
   *
   * It returns the main URI and its variants and translations.
   *
   * @return \Drupal\static_export\Exporter\Output\Uri\UriInterface[]
   *   Array of URIs.
   */
  public function getUris(): array;

  /**
   * Get a exported item (entity/config/locale) main URI.
   *
   * @return \Drupal\static_export\Exporter\Output\Uri\UriInterface|null
   *   The main URI or null if nothing found.
   */
  public function getMainUri(): ?UriInterface;

  /**
   * Get a exported item (entity/config/locale) variant URIs.
   *
   * @param \Drupal\Core\Language\LanguageInterface|null $language
   *   Optional language.
   *
   * @return \Drupal\static_export\Exporter\Output\Uri\UriInterface[]
   *   Array of URIs.
   */
  public function getVariantUris(LanguageInterface $language = NULL): array;

  /**
   * Get a exported item (entity/config) translation URIs.
   *
   * @return \Drupal\static_export\Exporter\Output\Uri\UriInterface[]
   *   Array of URIs.
   */
  public function getTranslationUris(): array;

}
