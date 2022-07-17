<?php

namespace Drupal\static_export\Exporter\Type\Locale\Output\Uri\Resolver;

use Drupal\static_export\Exporter\Output\Uri\Resolver\ExporterUriResolverInterface;

/**
 * Interface for a path resolver of exported locales.
 */
interface LocaleExporterUriResolverInterface extends ExporterUriResolverInterface {

  /**
   * Set language to work with.
   *
   * @param string $langcode
   *   Language name to work with.
   *
   * @return LocaleExporterUriResolverInterface
   *   Return $this so this method can be chainable
   */
  public function setLanguage(string $langcode): LocaleExporterUriResolverInterface;

}
