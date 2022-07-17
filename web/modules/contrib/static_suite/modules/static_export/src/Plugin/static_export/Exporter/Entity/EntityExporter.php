<?php

namespace Drupal\static_export\Plugin\static_export\Exporter\Entity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginInterface;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface;
use Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginBase;
use Drupal\static_export\File\FileCollection;
use Drupal\static_suite\StaticSuiteUserException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an entity exporter.
 *
 * @StaticEntityExporter(
 *  id = "default_entity",
 *  label = @Translation("Entity exporter"),
 *  description = @Translation("Exports entities to filesystem. Default entity
 *   exporter provided by Static Suite."),
 * )
 */
class EntityExporter extends EntityExporterPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager|object|null
   */
  protected $entityTypeManager;

  /**
   * The locale config manager.
   *
   * @var \Drupal\locale\LocaleConfigManager|object|null
   */
  protected $localeConfigManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * Entity exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface
   */
  protected $entityExporterPluginManager;

  /**
   * Data resolver manager.
   *
   * @var \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface
   */
  protected $dataResolverManager;

  /**
   * Exportable entity manager.
   *
   * @var \Drupal\static_export\Entity\ExportableEntityManagerInterface
   */
  protected $exportableEntityManager;

  /**
   * {@inheritdoc}
   */
  protected function setExtraDependencies(ContainerInterface $container): void {
    $this->entityTypeManager = $container->get("entity_type.manager");
    $this->localeConfigManager = $container->get("locale.config_manager");
    $this->currentRouteMatch = $container->get("current_route_match");
    $this->entityExporterPluginManager = $container->get("plugin.manager.static_entity_exporter");
    $this->dataResolverManager = $container->get("plugin.manager.static_data_resolver");
    $this->exportableEntityManager = $container->get("static_export.exportable_entity_manager");
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Item being exported (an entity)
   */
  public function getExporterItem() {
    return $this->options['entity'];
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemId() {
    return $this->getExporterItem()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemLabel() {
    return $this->getExporterItem()->label();
  }

  /**
   * {@inheritdoc}
   *
   * This exporter always needs an entity, so its loaded here if no provided by
   * params.
   */
  public function preProcessOptions(array $options): array {
    // Load an entity if enough options are provided.
    if (empty($options['entity']) && !empty($options['entity-type-id']) && !empty($options['entity-id'])) {
      // Load and set the entity.
      $options['entity'] = $this->entityUtils->loadEntity(
        $options['entity-type-id'],
        $options['entity-id']
      );

      // Unset non-required options since we have an entity.
      unset($options['entity-type-id'],
        $options['entity-id']);
    }

    // Original entity is only available during save. Exporters can be executed
    // later, after save is done, so original entity is already gone. Restore it
    // if available.
    // @see \Drupal\Core\Entity\EntityStorageBase::save()
    if (!empty($options['entity']) && !empty($options['original-entity'])) {
      $options['entity']->original = $options['original-entity'];
    }

    // Define language when is a master export. It should be provided from the
    // outside when is not a master export.
    if ($this->isMasterExport) {
      $options['language'] = $this->entityUtils->getNormalizedEntityLanguage($options['entity']);
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function checkParams(array $options): bool {
    // Check entity.
    if (empty($options['entity']) || !($options['entity'] instanceof EntityInterface)) {
      throw new StaticSuiteUserException("Param 'entity' must be an instance of Entity. Please, provide it directly, or provide the following parameters: 'entity-type-id' and 'entity-id'");
    }

    // Check language is provided for slave exports.
    if (empty($options['language']) && !$this->isMasterExport) {
      throw new StaticSuiteUserException("Param 'language' is required for slave export processes.");
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * There could be millions of entities exported to the same directory, so
   * we split them up into multiple directories, based on the entity id:
   *  - IDs from 0 to 9999: stored at the root level. Useful for small sites,
   *    where an extra subdirectory makes no sense
   *  - IDs from 10000 to 19999: stored into a "/10000" directory
   *  - IDs from 20000 to 29999: stored into a "/20000" directory
   * and so on...
   */
  protected function getOutputDefinition(): ?ExporterOutputConfigInterface {
    $entity = $this->options['entity'];
    $exportableEntity = $this->exportableEntityManager->getExportableEntity($entity);
    if ($exportableEntity) {
      $dir = $exportableEntity->getDirectory();
      // Expand optional token for sub directories.
      // It's done here because OPTIONAL_SUB_DIR_TOKEN is only meaningful for
      // exporters implementing EntityExporterInterface.
      $entityId = $entity->id();
      if (is_numeric($entityId) && $entityId >= self::FILES_PER_DIR) {
        $thousands = (int) floor($entityId / self::FILES_PER_DIR) * self::FILES_PER_DIR;
        $dir = str_replace(self::OPTIONAL_SUB_DIR_TOKEN, '/' . $thousands, $dir);
      }
      else {
        $dir = str_replace(self::OPTIONAL_SUB_DIR_TOKEN, '', $dir);
      }

      $filename = str_replace(self::ENTITY_ID_TOKEN, $entityId, $exportableEntity->getFilename());
      $format = $exportableEntity->getFormat();
      $definitions = $this->outputFormatterManager->getDefinitions();
      $extension = !empty($definitions[$format]) ? $definitions[$format]['extension'] : $format;

      return $this->exporterOutputConfigFactory->create($dir, $filename, $extension, $this->options['language'], $format);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function calculateDataFromResolver() {
    $dataResolver = $this->getDataResolver();
    if ($dataResolver) {
      $entity = $this->options['entity'];
      $variant = $this->options['variant'] ?? NULL;
      $dataFromResolver = $dataResolver->resolve($entity, $variant, $this->options['language']->getId());
      $definition = $dataResolver->getPluginDefinition();
      if (empty($definition['format']) && !is_array($dataFromResolver)) {
        throw new StaticSuiteUserException("Resolver '" . $dataResolver->getPluginId() . "' must return an array");
      }
      return $dataFromResolver;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function exportVariants(): FileCollection {
    $fileCollection = new FileCollection($this->uniqueId());
    if (empty($this->options['variant'])) {
      $entityBeingExported = $this->getExporterItem();
      foreach ($this->getVariantKeys() as $variantKey) {
        // We need a new instance, so create a new one instead of getting it.
        $variantExporter = $this->entityExporterPluginManager->createDefaultInstance();
        $method = ($this->getOperation() === ExporterPluginInterface::OPERATION_WRITE) ? 'write' : 'delete';
        $variantsFileCollectionGroup = $variantExporter->makeSlaveOf($this)
          ->$method(
            [
              'entity' => $entityBeingExported,
              'variant' => $variantKey,
              'language' => $this->options['language'],
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
   * Most entities are "content entities" (nodes, taxonomy terms, etc) and they
   * support translations in a standard way because they implement
   * Drupal\Core\TypedData\TranslatableInterface. On the contrary, there are
   * also other kind of entities, mainly "config entities" which implement
   * Drupal\Core\Config\Entity\ConfigEntityInterface (do not confuse them
   * with config *objects*). Those config entities use a different translation
   * strategy, based on overrides (which is the same strategy followed by
   * configuration objects).
   *
   * Being a completely different translation strategy, we split this method out
   * into two different ones.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Exception
   */
  protected function exportTranslations(): FileCollection {
    $fileCollection = new FileCollection($this->uniqueId());
    $entityBeingExported = $this->getExporterItem();
    if ($this->isMasterExport) {
      if ($entityBeingExported instanceof TranslatableInterface) {
        // Entities implementing TranslatableInterface do not necessarily
        // support translations. In such cases, do not export any translation.
        if ($entityBeingExported->isTranslatable()) {
          $fileCollection = $this->exportTranslatableEntityTranslations($entityBeingExported);
        }
      }
      elseif ($entityBeingExported instanceof ConfigEntityInterface) {
        $fileCollection = $this->exportConfigEntityTranslations($entityBeingExported);
      }
    }

    return $fileCollection;
  }

  /**
   * Export translations for entities implementing TranslatableInterface.
   *
   * @param \Drupal\Core\TypedData\TranslatableInterface $translatableEntity
   *   A translatable entity implementing TranslatableInterface.
   *
   * @return \Drupal\static_export\File\FileCollection
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function exportTranslatableEntityTranslations(TranslatableInterface $translatableEntity): FileCollection {
    $fileCollection = new FileCollection($this->uniqueId());

    // First, delete non-used languages from original entity, if entity
    // extends Drupal\Core\Entity\ContentEntityBase.
    $translationLanguages = $translatableEntity->getTranslationLanguages(TRUE);
    if ($translatableEntity instanceof ContentEntityBase) {
      $deletedTranslationLangcodes = [];
      $originalEntityBeingExported = $translatableEntity->original ?? NULL;
      if (is_object($originalEntityBeingExported) && $originalEntityBeingExported instanceof EditorialContentEntityBase) {
        $previousTranslationLanguages = $originalEntityBeingExported->getTranslationLanguages(TRUE);
        $deletedTranslationLangcodes = array_diff(array_keys($previousTranslationLanguages), array_keys($translationLanguages));
      }
      foreach ($deletedTranslationLangcodes as $deletedTranslationLangcode) {
        // We need a new instance, so create a new one instead of getting it.
        $deletedTranslationExporter = $this->entityExporterPluginManager->createDefaultInstance();
        $deletedTranslationsFileCollectionGroup = $deletedTranslationExporter->makeSlaveOf($this)
          ->delete(
            [
              'entity' => $originalEntityBeingExported ? $originalEntityBeingExported->getTranslation($deletedTranslationLangcode) : $translatableEntity,
              'language' => $previousTranslationLanguages[$deletedTranslationLangcode],
            ],
            TRUE,
            $this->mustLogToFile(),
            $this->isLock()
          );
        $fileCollection->mergeMultiple($deletedTranslationsFileCollectionGroup->getFileCollections());
      }
    }

    // Second, export languages from current entity. No need to check if
    // entity is translatable, since that is done in exportTranslations().
    foreach ($this->getTranslationLanguages() as $translationLanguage) {
      $translationExporter = $this->entityExporterPluginManager->createDefaultInstance();
      $method = ($this->getOperation() === ExporterPluginInterface::OPERATION_WRITE) ? 'export' : 'delete';
      $translationsFileCollectionGroup = $translationExporter->makeSlaveOf($this)
        ->$method(
          [
            'entity' => $translatableEntity->getTranslation($translationLanguage->getId()),
            'language' => $translationLanguage,
          ],
          TRUE,
          $this->mustLogToFile(),
          $this->isLock()
        );
      $fileCollection->mergeMultiple($translationsFileCollectionGroup->getFileCollections());
    }

    return $fileCollection;
  }

  /**
   * Export translations for entities instance of ConfigEntityInterface.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $configEntity
   *   An entity implementing ConfigEntityInterface.
   *
   * @return \Drupal\static_export\File\FileCollection
   *
   * @throws \Exception
   */
  protected function exportConfigEntityTranslations(ConfigEntityInterface $configEntity): FileCollection {
    $fileCollection = new FileCollection($this->uniqueId());

    // First, delete non used languages from original entity, if entity
    // extends Drupal\Core\Entity\ContentEntityBase.
    // Since we need the current route match to get the language of the config
    // object being edited, and this responds to an action triggered by a user,
    // avoid executing this code while on CLI.
    if (!$this->staticSuiteUtils->isRunningOnCli()) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entityBeingExported */
      $entityBeingExported = $this->getExporterItem();
      $translationLanguages = $this->entityUtils->getConfigEntityTranslationLanguages($entityBeingExported, TRUE);
      /** @var \Drupal\Core\Routing\RouteMatchInterface $currentRouteMatch */
      $langcodeBeingEdited = $this->currentRouteMatch->getParameter('langcode');
      $languageBeingEdited = $this->languageManager->getLanguage($langcodeBeingEdited);
      if ($languageBeingEdited && empty($translationLanguages[$langcodeBeingEdited])) {
        // We need a new instance, so create a new one instead of getting it.
        $deletedTranslationExporter = $this->entityExporterPluginManager->createDefaultInstance();
        $deletedTranslationsFileCollectionGroup = $deletedTranslationExporter->makeSlaveOf($this)
          ->delete(
            [
              'entity' => $entityBeingExported,
              'language' => $languageBeingEdited,
            ],
            TRUE,
            $this->mustLogToFile(),
            $this->isLock()
          );
        $fileCollection->mergeMultiple($deletedTranslationsFileCollectionGroup->getFileCollections());
      }
    }

    // Second, export languages from current entity.
    $callable = function () use ($configEntity) {
      $translationExporter = $this->entityExporterPluginManager->createDefaultInstance();
      $method = ($this->getOperation() === ExporterPluginInterface::OPERATION_WRITE) ? 'write' : 'delete';
      return $translationExporter->makeSlaveOf($this)
        ->$method(
          [
            'entity' => $configEntity,
            'language' => $this->languageManager->getCurrentLanguage(),
          ],
          TRUE,
          $this->mustLogToFile(),
          $this->isLock()
        );
    };

    // Execute translation export process inside a language context.
    foreach ($this->getTranslationLanguages() as $language) {
      $fileCollectionGroup = $this->languageContext->executeInLanguageContext(
        $callable, $language->getId()
      );
      $fileCollection->mergeMultiple($fileCollectionGroup->getFileCollections());
    }

    return $fileCollection;
  }

  /**
   * Get the data resolver plugin being used by this entity exporter.
   *
   * @return \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginInterface
   *   Data resolver plugin
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function getDataResolver(): ?DataResolverPluginInterface {
    $entity = $this->options['entity'];
    $exportableEntity = $this->exportableEntityManager->getExportableEntity($entity);
    if ($exportableEntity) {
      $dataResolverId = $exportableEntity->getDataResolver();
      try {
        $dataResolver = $this->dataResolverManager->getInstance(['plugin_id' => $dataResolverId]);
      }
      catch (PluginException $e) {
        throw new StaticSuiteUserException("Unknown Static Export data resolver: " . $dataResolverId);
      }
      return $dataResolver;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isExportable(array $options): bool {
    $entity = $options['entity'];
    if (empty($entity) || !($entity instanceof EntityInterface)) {
      return FALSE;
    }

    // Check if there is an exportable entity for this $entity, and that it's
    // enabled.
    return $this->exportableEntityManager->isExportable($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function getVariantKeyDefinitions(): array {
    $dataResolver = $this->getDataResolver();
    if ($dataResolver) {
      $entity = $this->options['entity'];
      return $dataResolver->getVariantKeys($entity);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslationLanguageDefinitions(): array {
    $entityBeingExported = $this->getExporterItem();
    $translationLanguages = NULL;
    if ($entityBeingExported instanceof TranslatableInterface && $entityBeingExported->isTranslatable()) {
      $translationLanguages = $entityBeingExported->getTranslationLanguages(TRUE);
    }
    elseif ($entityBeingExported instanceof ConfigEntityInterface) {
      /*
      Configuration entities don't use translations but overrides. That means
      that a configuration entity is always available in all languages:
      overrides are applied over the original configuration entity.

      To keep it in sync with this strategy, configuration entities are always
      exported in all languages, and overrides, if any, are applied to each of
      them.
      @see ConfigExporter::getTranslationLanguageDefinitions() for more
      information on this strategy, since configuration objects use the same
      one.
       */
      $translationLanguages = $this->languageManager->getLanguages();
    }

    // Remove the language of the entity being exported.
    if ($translationLanguages) {
      unset($translationLanguages[$this->options['language']->getId()]);
      return $translationLanguages;
    }

    return [];
  }

}
