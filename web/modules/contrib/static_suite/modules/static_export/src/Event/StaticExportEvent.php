<?php

namespace Drupal\static_export\Event;

use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface;
use Drupal\static_export\File\FileCollection;
use Drupal\static_export\File\FileCollectionGroup;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Generic static export event to be fired.
 */
class StaticExportEvent extends Event {

  // Event constants.
  public const EVENT_CONFIG = 'event:config';

  public const EVENT_DATA_FROM_RESOLVER = 'event:data-from-resolver';

  public const EVENT_DATA_FROM_FORMATTER = 'event:data-from-formatter';

  public const EVENT_VARIANT_KEYS = 'event:variant_keys';

  public const EVENT_TRANSLATION_LANGUAGES = 'event:translation_languages';

  public const EVENT_FILE_COLLECTION = 'event:file-collection';

  public const EVENT_FILE_COLLECTION_GROUP = 'event:file-collection-group';

  /**
   * The exporter that triggers the event.
   *
   * @var \Drupal\static_export\Exporter\ExporterPluginInterface
   */
  protected $exporter;

  /**
   * An ExporterOutputConfig object.
   *
   * @var \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface
   */
  protected $outputConfig;

  /**
   * An array with structured data from the resolver.
   *
   * @var array
   */
  protected $dataFromResolver;

  /**
   * A string with formatted data from the formatter.
   *
   * @var string
   */
  protected $dataFromFormatter;

  /**
   * Array of variant keys.
   *
   * @var array
   */
  protected $variantKeys;

  /**
   * Array of translation languages.
   *
   * @var \Drupal\Core\Language\LanguageInterface[]
   */
  protected $translationLanguages;

  /**
   * A FileCollection with processed files.
   *
   * @var \Drupal\static_export\File\FileCollection
   */
  protected $fileCollection;

  /**
   * A FileCollectionGroup with processed files.
   *
   * @var \Drupal\static_export\File\FileCollectionGroup
   */
  protected $fileCollectionGroup;

  /**
   * A flag to abort the process.
   *
   * @var bool
   */
  protected $mustAbort = FALSE;

  /**
   * Constructs the object.
   *
   * @param \Drupal\static_export\Exporter\ExporterPluginInterface $exporter
   *   The exporter.
   */
  public function __construct(ExporterPluginInterface $exporter) {
    $this->exporter = $exporter;
  }

  /**
   * Get the exporter.
   *
   * @return \Drupal\static_export\Exporter\ExporterPluginInterface
   *   The exporter.
   */
  public function getExporter(): ExporterPluginInterface {
    return $this->exporter;
  }

  /**
   * Get config data.
   *
   * @return \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface|null
   *   Config data.
   */
  public function getOutputConfig(): ?ExporterOutputConfigInterface {
    return $this->outputConfig;
  }

  /**
   * Sets config data.
   *
   * @param \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface $outputConfig
   *   Output config.
   */
  public function setOutputConfig(ExporterOutputConfigInterface $outputConfig): void {
    $this->outputConfig = $outputConfig;
  }

  /**
   * Get data from resolver.
   *
   * @return array|string|null
   *   Data from resolver.
   */
  public function getDataFromResolver() {
    return $this->dataFromResolver;
  }

  /**
   * Sets data from resolver.
   *
   * @param array|string $dataFromResolver
   *   Data from resolver.
   */
  public function setDataFromResolver($dataFromResolver): void {
    $this->dataFromResolver = $dataFromResolver;
  }

  /**
   * Get data from formatter.
   *
   * @return string|null
   *   Data from formatter.
   */
  public function getDataFromFormatter(): ?string {
    return $this->dataFromFormatter;
  }

  /**
   * Set data from formatter.
   *
   * @param string $dataFromFormatter
   *   Data from formatter.
   */
  public function setDataFromFormatter(string $dataFromFormatter): void {
    $this->dataFromFormatter = $dataFromFormatter;
  }

  /**
   * Get variant keys.
   *
   * @return string[]|null
   *   Array of variant keys.
   */
  public function getVariantKeys(): ?array {
    return $this->variantKeys;
  }

  /**
   * Set variant keys.
   *
   * @param string[] $variantKeys
   *   Array of variant keys.
   */
  public function setVariantKeys(array $variantKeys): void {
    $this->variantKeys = $variantKeys;
  }

  /**
   * Get translation languages.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]|null
   *   Array of translation languages.
   */
  public function getTranslationLanguages(): ?array {
    return $this->translationLanguages;
  }

  /**
   * Set translation languages.
   *
   * @param \Drupal\Core\Language\LanguageInterface[] $translationLanguages
   *   Array of translation languages.
   */
  public function setTranslationLanguages(array $translationLanguages): void {
    $this->translationLanguages = $translationLanguages;
  }

  /**
   * Get FileCollection.
   *
   * @return \Drupal\static_export\File\FileCollection|null
   *   Exporter's FileCollection.
   */
  public function getFileCollection(): ?FileCollection {
    return $this->fileCollection;
  }

  /**
   * Set exporter's FileCollection.
   *
   * @param \Drupal\static_export\File\FileCollection $fileCollection
   *   A FileCollection.
   */
  public function setFileCollection(FileCollection $fileCollection): void {
    $this->fileCollection = $fileCollection;
  }

  /**
   * Get FileCollectionGroup.
   *
   * @return \Drupal\static_export\File\FileCollectionGroup|null
   *   A FileCollectionGroup.
   */
  public function getFileCollectionGroup(): ?FileCollectionGroup {
    return $this->fileCollectionGroup;
  }

  /**
   * A flag to abort this process.
   *
   * @return bool
   *   True if we must abort.
   */
  public function mustAbort(): bool {
    return $this->mustAbort;
  }

  /**
   * Set a flag to abort this process ASAP.
   *
   * @param bool $mustAbort
   *   A flag to abort this process.
   */
  public function setMustAbort(bool $mustAbort): void {
    $this->mustAbort = $mustAbort;
  }

  /**
   * Set exporter's FileCollectionGroup.
   *
   * @param \Drupal\static_export\File\FileCollectionGroup $fileCollectionGroup
   *   A FileCollectionGroup.
   */
  public function setFileCollectionGroup(FileCollectionGroup $fileCollectionGroup): void {
    $this->fileCollectionGroup = $fileCollectionGroup;
  }

  /**
   * Set event data.
   *
   * @param array $data
   *   An array with data.
   */
  public function setData(array $data): void {
    if (isset($data[self::EVENT_CONFIG])) {
      $this->setOutputConfig($data[self::EVENT_CONFIG]);
    }

    if (isset($data[self::EVENT_DATA_FROM_RESOLVER])) {
      $this->setDataFromResolver($data[self::EVENT_DATA_FROM_RESOLVER]);
    }

    if (isset($data[self::EVENT_DATA_FROM_FORMATTER])) {
      $this->setDataFromFormatter($data[self::EVENT_DATA_FROM_FORMATTER]);
    }

    if (isset($data[self::EVENT_VARIANT_KEYS])) {
      $this->setVariantKeys($data[self::EVENT_VARIANT_KEYS]);
    }

    if (isset($data[self::EVENT_TRANSLATION_LANGUAGES])) {
      $this->setTranslationLanguages($data[self::EVENT_TRANSLATION_LANGUAGES]);
    }

    if (isset($data[self::EVENT_FILE_COLLECTION])) {
      $this->setFileCollection($data[self::EVENT_FILE_COLLECTION]);
    }

    if (isset($data[self::EVENT_FILE_COLLECTION_GROUP])) {
      $this->setFileCollectionGroup($data[self::EVENT_FILE_COLLECTION_GROUP]);
    }
  }

}
