<?php

namespace Drupal\static_export\Plugin\static_export\Exporter\Locale;

use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface;
use Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginBase;
use Drupal\static_export\File\FileCollection;
use Drupal\static_suite\StaticSuiteUserException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exporter for localized strings.
 *
 * @StaticLocaleExporter(
 *  id = "default_locale",
 *  label = @Translation("Localized string exporter"),
 *  description = @Translation("Exports localized strings to filesystem.
 *   Default locale exporter provided by Static Suite."),
 * )
 */
class LocaleExporter extends LocaleExporterPluginBase {

  /**
   * Locale storage.
   *
   * @var \Drupal\locale\StringStorageInterface
   */
  protected $localeStorage;

  /**
   * The locale exporter plugin manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface
   */
  protected $localeExporterPluginManager;

  /**
   * Flag to indicate that this exporter should always write.
   *
   * @var bool
   */
  protected $isForceWrite = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setExtraDependencies(ContainerInterface $container): void {
    $this->localeStorage = $container->get("locale.storage");
    $this->localeExporterPluginManager = $container->get("plugin.manager.static_locale_exporter");
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\locale\StringInterface[]
   *   Array of StringInterface
   */
  public function getExporterItem() {
    $langcode = $this->options['langcode'];
    if (empty($langcode)) {
      return NULL;
    }

    if (empty($this->exporterItem[$langcode])) {
      $this->exporterItem[$langcode] = $this->localeStorage->getTranslations([
        'language' => $langcode,
        'translated' => TRUE,
      ]);
    }

    return $this->exporterItem[$langcode];
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemId() {
    return 'localized_strings';
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemLabel() {
    return 'Localized strings ' . $this->options['langcode'];
  }

  /**
   * {@inheritdoc}
   */
  public function checkParams(array $options): bool {
    $langcode = $options['langcode'] ?? NULL;
    if (!isset($langcode)) {
      throw new StaticSuiteUserException("Param 'langcode' is not defined.");
    }

    $enabledLanguages = $this->languageManager->getLanguages();
    if (empty($enabledLanguages[$langcode])) {
      throw new StaticSuiteUserException("Language 'langcode' is not enabled on this site.");
    }

    return TRUE;
  }

  /**
   * Tell whether this exporter should always write.
   *
   * @return bool
   *   True if write is forced.
   */
  public function isForceWrite(): bool {
    return $this->isForceWrite;
  }

  /**
   * Flag to indicate that this exporter should always write.
   *
   * @param bool $isForceWrite
   *   Flag for always write.
   */
  public function setIsForceWrite(bool $isForceWrite) {
    $this->isForceWrite = $isForceWrite;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOutputDefinition(): ExporterOutputConfigInterface {
    $config = $this->configFactory->get('static_export.settings');
    $filename = 'locale.' . $this->options['langcode'];
    $format = $config->get('exportable_locale.format');

    // Load the OutputFormatter plugin definition to get its extension.
    $definitions = $this->outputFormatterManager->getDefinitions();
    $extension = !empty($definitions[$format]) ? $definitions[$format]['extension'] : $format;

    $language = $this->languageManager->getLanguage($this->options['langcode']);
    return $this->exporterOutputConfigFactory->create('', $filename, $extension, $language, $format);
  }

  /**
   * {@inheritdoc}
   */
  protected function calculateDataFromResolver() {
    $localeData = [];
    foreach ($this->getExporterItem() as $locale) {
      $localeData[$locale->source] = $locale->getString();
    }

    return $localeData;
  }

  /**
   * {@inheritdoc}
   */
  protected function exportVariants(): FileCollection {
    $fileCollection = new FileCollection($this->uniqueId());
    if (empty($this->options['variant'])) {
      // Override $this->getVariantKeyDefinitions() to add support for variants.
      foreach ($this->getVariantKeys() as $variantKey) {
        // We need a new instance, so create a new one instead of getting it.
        $variantExporter = $this->localeExporterPluginManager->createDefaultInstance();
        $method = ($this->getOperation() === ExporterPluginInterface::OPERATION_WRITE) ? 'write' : 'delete';
        $variantsFileCollectionGroup = $variantExporter->makeSlaveOf($this)
          ->$method(
            [
              'langcode' => $this->options['langcode'],
              'variant' => $variantKey,
            ],
            TRUE,
            $this->mustLogToFile(),
            $this->isLock()
          );
        $fileCollection->mergeMultiple($variantsFileCollectionGroup->getFileCollections());
      }
    }
    return $fileCollection;
  }

}
