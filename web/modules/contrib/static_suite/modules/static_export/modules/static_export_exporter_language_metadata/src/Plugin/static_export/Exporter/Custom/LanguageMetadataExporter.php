<?php

namespace Drupal\static_export_exporter_language_metadata\Plugin\static_export\Exporter\Custom;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface;
use Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginBase;
use Drupal\static_suite\StaticSuiteUserException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Language metadata exporter.
 *
 * @StaticCustomExporter(
 *  id = "language-metadata",
 *  label = @Translation("Language metadata exporter"),
 *  description = @Translation("Exports language metadata"),
 * )
 */
class LanguageMetadataExporter extends CustomExporterPluginBase {

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Entity Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get("module_handler");
    $instance->entityTypeManager = $container->get("entity_type.manager");

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItem() {
    return 'language_metadata_exporter';
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemId() {
    return 'language_metadata_exporter';
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemLabel() {
    return "Language metadata exporter";
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function getOutputDefinition(): ?ExporterOutputConfigInterface {
    $format = $this->configFactory->get('static_export.settings')
      ->get('exportable_config.format');
    if (empty($format)) {
      throw new StaticSuiteUserException("No output export format defined for this data.");
    }

    try {
      $outputFormatter = $this->outputFormatterManager->getInstance(['plugin_id' => $format]);
    }
    catch (PluginException $e) {
      throw new StaticSuiteUserException("Unknown Static Export output format: " . $format);
    }

    $extension = $outputFormatter ? $outputFormatter->getPluginDefinition()['extension'] : $format;
    return $this->exporterOutputConfigFactory->create(
      'config',
      'languages',
      $extension
    )->setBaseDir('system');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function calculateDataFromResolver() {
    // Reset config factory's internal cache so fresh data is retrieved.
    // Sometimes, when a commonly used configuration object is exported
    // (e.g.- system.site), it's already loaded and cached before this code
    // executes, leading to exporting stale data.
    $this->configFactory->reset('language.negotiation');
    $prefixes = $this->configFactory->get('language.negotiation')
      ->get('url.prefixes');
    $languages = [];

    foreach ($this->languageManager->getLanguages() as $language) {
      // Get the translated names of languages.
      $currentLangcode = $language->getId();
      $languageNames = [$currentLangcode => $language->getName()];
      $callable = function () use ($currentLangcode) {
        // Reset language entity config to get fresh data and translations.
        $this->configFactory->reset('language.entity.' . $currentLangcode);
        $subLanguage = $this->languageManager->getLanguage($currentLangcode);
        return $subLanguage ? $subLanguage->getName() : NULL;
      };
      foreach ($this->languageManager->getLanguages() as $subLanguage) {
        $subLangcode = $subLanguage->getId();
        $languageNames[$subLangcode] = $this->languageContext->executeInLanguageContext($callable, $subLangcode);
      }

      $languageToExport = [
        'langcode' => $language->getId(),
        'name' => $languageNames,
        'isDefault' => $language->isDefault(),
        'prefix' => $prefixes[$language->getId()],
        'weight' => $language->getWeight(),
        'direction' => $language->getDirection(),
      ];

      $configurableLanguageEntity = $this->getConfigurableLanguageEntity($language);
      if (
        $configurableLanguageEntity &&
        $this->moduleHandler->moduleExists('hidden_language')
      ) {
        $languageToExport['hidden'] = $configurableLanguageEntity->getThirdPartySetting('hidden_language', 'hidden', FALSE);
      }
      $languages[] = $languageToExport;
    }
    return $languages;
  }

  /**
   * Get a configurable language entity based on its language entity.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Language entity.
   *
   * @return \Drupal\language\ConfigurableLanguageInterface|null
   *   ConfigurableLanguage Entity
   */
  protected function getConfigurableLanguageEntity(LanguageInterface $language): ?ConfigurableLanguageInterface {
    $entity = NULL;
    try {
      $entity = $this->entityTypeManager
        ->getStorage('configurable_language')
        ->load($language->getId());
    }
    catch (\Exception $exception) {
      // Do nothing.
    }

    return $entity;
  }

}
