<?php

namespace Drupal\static_export\Plugin\static_export\Exporter\Config;

use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface;
use Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginBase;
use Drupal\static_export\File\FileCollection;
use Drupal\static_suite\StaticSuiteUserException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exporter for Drupal configuration objects.
 *
 * @StaticConfigExporter(
 *  id = "default_config",
 *  label = @Translation("Configuration exporter"),
 *  description = @Translation("Exports configuration entities to filesystem.
 *   Default configuration exporter provided by Static Suite."),
 * )
 */
class ConfigExporter extends ConfigExporterPluginBase {

  /**
   * The config exporter plugin manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface
   */
  protected $configExporterPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setExtraDependencies(ContainerInterface $container): void {
    $this->configExporterPluginManager = $container->get("plugin.manager.static_config_exporter");
  }

  /**
   * Array to hold several config objects.
   *
   * @var array
   */
  protected $exporterItem = [];

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Config\Config
   *   Drupal configuration object.
   */
  public function getExporterItem() {
    $configName = $this->options['name'] ?? NULL;
    if (empty($configName)) {
      return NULL;
    }

    if (empty($this->exporterItem[$configName])) {
      $this->exporterItem[$configName] = $this->configFactory->get($configName);
    }

    return $this->exporterItem[$configName] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemId() {
    return $this->getExporterItem()->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemLabel() {
    return $this->getExporterItem()->getName();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function checkParams(array $options): bool {
    if (!isset($options['name'])) {
      throw new StaticSuiteUserException("Param 'name' is required.");
    }

    // Do not check if 'name' is exportable, since that is
    // done in $this->isExportable(), which is automatically called.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOutputDefinition(): ExporterOutputConfigInterface {
    $filename = $this->options['name'];
    $format = $this->configFactory->get('static_export.settings')
      ->get('exportable_config.format');

    // Load the OutputFormatter plugin definition to get its extension.
    $definitions = $this->outputFormatterManager->getDefinitions();
    $extension = !empty($definitions[$format]) ? $definitions[$format]['extension'] : $format;

    // By default, this method is executed inside a language context, so it
    // just uses the current language. This behaviour can be overridden by
    // passing a 'langcode' option, which enforces the use of that language.
    $language = $this->languageManager->getCurrentLanguage();
    if (!empty($this->options['langcode'])) {
      $languageFromLangcode = $this->languageManager->getLanguage($this->options['langcode']);
      if ($languageFromLangcode) {
        $language = $languageFromLangcode;
      }
    }

    return $this->exporterOutputConfigFactory->create('', $filename, $extension, $language, $format);
  }

  /**
   * {@inheritdoc}
   *
   * Get configuration data in a proper language context, so config overrides
   * are applied in the language defined by options.
   *
   * @throws \Exception
   */
  protected function calculateDataFromResolver() {
    // Reset config factory's internal cache so fresh data is retrieved.
    // Sometimes, when a commonly used configuration object is exported
    // (e.g.- system.site), it's already loaded and cached before this code
    // executes, leading to exporting stale data.
    $this->configFactory->reset($this->options['name']);
    return $this->getExporterItem()->getOriginal('', TRUE);
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
        $variantExporter = $this->configExporterPluginManager->createDefaultInstance();
        $method = ($this->getOperation() === ExporterPluginInterface::OPERATION_WRITE) ? 'write' : 'delete';
        $variantsFileCollectionGroup = $variantExporter->makeSlaveOf($this)
          ->$method(
            [
              'name' => $this->options['name'],
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

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  public function exportTranslations(): FileCollection {
    $fileCollection = new FileCollection($this->uniqueId());
    if ($this->isMasterExport) {
      $callable = function () {
        $slaveExporter = $this->configExporterPluginManager->createDefaultInstance();
        $method = ($this->getOperation() === ExporterPluginInterface::OPERATION_WRITE) ? 'write' : 'delete';
        return $slaveExporter->makeSlaveOf($this)
          ->$method(
            ['name' => $this->options['name']],
            TRUE,
            $this->mustLogToFile(),
            $this->isLock()
          );
      };

      // Execute translation export process inside a language context.
      foreach ($this->getTranslationLanguages() as $language) {
        $fileCollectionGroup = $this->languageContext->executeInLanguageContext($callable, $language->getId());
        $fileCollection->mergeMultiple($fileCollectionGroup->getFileCollections());
      }

    }
    return $fileCollection;
  }

  /**
   * {@inheritdoc}
   *
   * @param string $item
   *   Config name.
   */
  public function isExportable(array $options): bool {
    $exportableConfigNames = $this->configFactory->get('static_export.settings')
      ->get('exportable_config.objects_to_export');
    if (in_array($options['name'], $exportableConfigNames, TRUE)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Configuration objects are somewhat special since they don't use
   * translations but overrides. That means that a configuration object is
   * always available in all languages: overrides are applied over the original
   * configuration object.
   *
   * To keep it in sync with this strategy, configuration objects are always
   * exported in all languages, and overrides, if any, are applied to each of
   * them. This leads to data being repeated in several languages, but it makes
   * sense because avoids having to implement a more error-prone strategy (for
   * example, exporting only languages with overrides and then dynamically
   * loading the default configuration when no translated one is found, which
   * is way trickier).
   */
  protected function getTranslationLanguageDefinitions(): array {
    $translationLanguages = $this->languageManager->getLanguages();

    // Skip the language that comes from options to avoid repeating it.
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
    unset($translationLanguages[$currentLanguage]);

    return $translationLanguages;
  }

}
