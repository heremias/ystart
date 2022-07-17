<?php

namespace Drupal\static_export\Exporter\Type\Config\Output\Uri\Resolver;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\static_export\Exporter\Output\Uri\Resolver\ExporterUriResolverBase;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface;
use Drupal\static_suite\Entity\EntityUtilsInterface;
use Drupal\static_suite\Language\LanguageContextInterface;

/**
 * URI resolver service for exported configuration objects.
 */
class ConfigExporterUriResolver extends ExporterUriResolverBase implements ConfigExporterUriResolverInterface {

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Config exporter instance.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginInterface
   */
  protected $configExporter;

  /**
   * Config exporter Manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface
   */
  protected $configExporterManager;

  /**
   * Language context.
   *
   * @var \Drupal\static_suite\Language\LanguageContextInterface
   */
  protected $languageContext;

  /**
   * The entity utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtilsInterface
   */
  protected $entityUtils;

  /**
   * Config name to work with.
   *
   * @var string
   */
  protected $configName;

  /**
   * Uri Resolver service constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language Manager.
   * @param \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface $configExporterManager
   *   Config exporter Manager.
   * @param \Drupal\static_suite\Language\LanguageContextInterface $languageContext
   *   Language context.
   * @param \Drupal\static_suite\Entity\EntityUtilsInterface $entityUtils
   *   Entity utils.
   */
  public function __construct(
    LanguageManagerInterface $languageManager,
    ConfigExporterPluginManagerInterface $configExporterManager,
    LanguageContextInterface $languageContext,
    EntityUtilsInterface $entityUtils
  ) {
    $this->languageManager = $languageManager;
    $this->configExporterManager = $configExporterManager;
    $this->languageContext = $languageContext;
    $this->entityUtils = $entityUtils;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function setConfigName(string $configName): ConfigExporterUriResolverInterface {
    $this->configName = $configName;
    $this->configExporter = $this->configExporterManager->createDefaultInstance();
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function getMainUri(): ?UriInterface {
    return $this->configExporter->setOptions(['name' => $this->configName])
      ->getUri();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Exception
   */
  public function getVariantUris(LanguageInterface $language = NULL): array {
    $uris = [];
    $configExporter = $this->configExporter->setOptions(['name' => $this->configName]);

    $callable = function () use ($configExporter, &$uris) {
      foreach ($configExporter->getVariantKeys() as $variantKey) {
        $uris[] = $this->configExporter->setOptions([
          'name' => $this->configName,
          'variant' => $variantKey,
        ])->getUri();
      }
    };

    if ($language) {
      $this->languageContext->executeInLanguageContext($callable, $language->getId());
    }
    else {
      $callable();
    }

    // Remove null values by using array_filter.
    return array_filter($uris);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Exception
   */
  public function getTranslationUris(): array {
    $uris = [];
    $configExporter = $this->configExporter->setOptions(['name' => $this->configName]);

    $callable = function () use (&$uris) {
      $uris[] = $this->configExporter->setOptions(['name' => $this->configName])
        ->getUri();
    };

    foreach ($configExporter->getTranslationLanguages() as $translationLanguage) {
      $this->languageContext->executeInLanguageContext($callable, $translationLanguage->getId());
      // Variants from translations must also be added to the result.
      $translationVariants = $this->getVariantUris($translationLanguage);
      foreach ($translationVariants as $translationVariant) {
        $uris[] = $translationVariant;
      }
    }

    // Remove null values by using array_filter.
    return array_filter($uris);
  }

}
