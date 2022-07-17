<?php

namespace Drupal\static_export\Exporter\Type\Locale\Output\Uri\Resolver;

use Drupal\Core\Language\LanguageInterface;
use Drupal\static_export\Exporter\Output\Uri\Resolver\ExporterUriResolverBase;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface;

/**
 * URI resolver service for exported locale data.
 */
class LocaleExporterUriResolver extends ExporterUriResolverBase implements LocaleExporterUriResolverInterface {

  /**
   * Locale exporter Manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface
   */
  protected $localeExporterManager;

  /**
   * Locale exporter instance.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginInterface
   */
  protected $localeExporter;

  /**
   * Language id to work with.
   *
   * @var string
   */
  protected $langcode;

  /**
   * URI Resolver service constructor.
   *
   * @param \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface $localeExporterManager
   *   Locale exporter Manager.
   */
  public function __construct(LocaleExporterPluginManagerInterface $localeExporterManager) {
    $this->localeExporterManager = $localeExporterManager;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function setLanguage(string $langcode): LocaleExporterUriResolverInterface {
    $this->langcode = $langcode;
    $this->localeExporter = $this->localeExporterManager
      ->createDefaultInstance();
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function getMainUri(): ?UriInterface {
    return $this->localeExporter->setOptions(['langcode' => $this->langcode])
      ->getUri();
  }

  /**
   * {@inheritdoc}
   *
   * @param string $langcode
   *   Optional language id.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function getVariantUris(LanguageInterface $language = NULL): array {
    $uris = [];
    $this->localeExporter->setOptions(['langcode' => $this->langcode]);
    foreach ($this->localeExporter->getVariantKeys() as $variantKey) {
      $uris[] = $this->localeExporter
        ->setOptions([
          'langcode' => $language ? $language->getId() : $this->langcode,
          'variant' => $variantKey,
        ])
        ->getUri();
    }
    // Remove null values by using array_filter.
    return array_filter($uris);
  }

  /**
   * {@inheritdoc}
   *
   * At this moment, Locale exporter does not export translations, because
   * every language is completely independent from each other.
   *
   * Any way, we implement a complete method just in case
   * $localeExporter->getTranslationLanguages() returns something, which is a
   * method that can be easily overridden.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function getTranslationUris(): array {
    $uris = [];
    $this->localeExporter->setOptions(['langcode' => $this->langcode]);
    foreach ($this->localeExporter->getTranslationLanguages() as $translationKey) {
      $uris[] = $this->localeExporter
        ->setOptions(['langcode' => $translationKey])
        ->getUri();
      // Variants from translations must also be added to the result.
      $translationVariants = $this->getVariantUris($translationKey);
      foreach ($translationVariants as $translationVariant) {
        $uris[] = $translationVariant;
      }
    }
    // Remove null values by using array_filter.
    return array_filter($uris);
  }

}
