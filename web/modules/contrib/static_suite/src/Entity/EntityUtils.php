<?php

namespace Drupal\static_suite\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\locale\LocaleConfigManager;
use Drupal\path_alias\AliasManagerInterface;
use Throwable;

/**
 * A set of useful utils related to entities.
 */
class EntityUtils implements EntityUtilsInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The locale config manager.
   *
   * @var \Drupal\locale\LocaleConfigManager|object|null
   */
  protected $localeConfigManager;

  /**
   * Entity bundle id cache array.
   *
   * @var array
   */
  protected $entityBundleIdCache = [];

  /**
   * Constructs the entity utils.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language Manager.
   * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
   *   The alias manager.
   * @param \Drupal\locale\LocaleConfigManager $localeConfigManager
   *   The locale config manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, AliasManagerInterface $aliasManager, LocaleConfigManager $localeConfigManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->aliasManager = $aliasManager;
    $this->localeConfigManager = $localeConfigManager;
  }

  /**
   * {@inheritdoc}
   */
  public function loadEntity(string $entityTypeId, string $entityId, string $langcode = NULL): ?EntityInterface {
    try {
      // Keep in mind that this loads an entity with its original language, and
      // not the default language.
      $entity = $this->entityTypeManager->getStorage($entityTypeId)
        ->load($entityId);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $entity = NULL;
    }
    if ($entity && $langcode) {
      // All entities have a language even though they are not translatable.
      // First, find if entity language is the same they are asking for. This
      // works even for non translatable entities.
      $entityCurrentLanguage = $entity->language();
      if ($entityCurrentLanguage && $entityCurrentLanguage->getId() === $langcode) {
        return $entity;
      }
      // Second, find translations for translatable entities.
      if ($entity instanceof ContentEntityBase && $entity->isTranslatable()) {
        // Do not include the default language: it's been already checked above.
        $translationLanguages = $entity->getTranslationLanguages(FALSE);
        if (isset($translationLanguages[$langcode])) {
          return $entity->getTranslation($langcode);
        }
      }
      return NULL;
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds(string $entityTypeId, $options = []): array {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $query = $storage->getQuery();

    if (!empty($options['bundle'])) {
      $bundleEntityKey = $storage->getEntityType()->getKey('bundle');
      if (!empty($bundleEntityKey)) {
        $query->condition($bundleEntityKey, $options['bundle']);
      }
    }

    $hasStatus = $storage->getEntityType()->getKey('status');
    if ($hasStatus && !empty($options['status'])) {
      $query->condition('status', $options['status']);
    }

    if (isset($options['conditions'])) {
      foreach ($options['conditions'] as $condition) {
        if (!in_array($condition['field'], ['status', 'type'])) {
          $query->condition($condition['field'], $condition['value'], $condition['operator'] ?? NULL);
        }
      }
    }

    if (isset($options['sort'])) {
      $query->sort($options['sort']['field'], $options['sort']['direction']);
    }

    if (isset($options['range'])) {
      $query->range($options['range']['start'], $options['range']['length']);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getConfigEntityTranslationLanguages(ConfigEntityInterface $configEntity, bool $include_default = TRUE): array {
    $translationLanguages = [];
    $entityTypeInfo = $this->entityTypeManager->getDefinition($configEntity->getEntityTypeId());
    if ($entityTypeInfo && $entityTypeInfo instanceof ConfigEntityTypeInterface) {
      $configObjectName = $entityTypeInfo->getConfigPrefix() . '.' . $configEntity->id();
      $defaultLanguage = $this->languageManager->getDefaultLanguage();
      foreach ($this->languageManager->getLanguages() as $langcode => $language) {
        if (!$include_default && $language === $defaultLanguage) {
          continue;
        }

        if ($this->localeConfigManager->hasTranslation($configObjectName, $language->getId())) {
          $translationLanguages[$langcode] = $language;
        }
      }
    }

    return $translationLanguages;
  }

  /**
   * {@inheritdoc}
   */
  public function hasStatusChanged(EditorialContentEntityBase $entity): bool {
    $statusHasChanged = FALSE;
    $entityOldStatus = $entity->isPublished();
    $originalEntity = $entity->original ?? NULL;
    if (is_object($originalEntity) && $originalEntity instanceof EditorialContentEntityBase) {
      $entityOldStatus = $originalEntity->isPublished();
    }
    $entityNewStatus = $entity->isPublished();
    if ($entityOldStatus !== $entityNewStatus) {
      $statusHasChanged = TRUE;
    }
    return $statusHasChanged;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityDataByPagePath(string $pagePath, string $langcode = NULL): ?array {
    $entityData = NULL;
    // $path can arrive without a leading slash, but
    // Drupal\Core\Path\AliasManager needs that leading slash.
    if (!empty($pagePath)) {
      if ($pagePath[0] !== "/") {
        $pagePath = "/" . $pagePath;
      }
      try {
        $pagePath = $this->aliasManager->getPathByAlias($pagePath, $langcode);
        $params = Url::fromUri("internal:" . $pagePath)->getRouteParameters();
        $entityTypeId = key($params);
        $availableEntityTypes = $this->entityTypeManager->getDefinitions();
        if ($entityTypeId && isset($availableEntityTypes[$entityTypeId])) {
          $entityId = $params[$entityTypeId];
          if ($entityId) {
            $entityData = [
              'entityTypeId' => $entityTypeId,
              'entityId' => $entityId,
            ];
          }
        }
      }
      catch (Throwable $e) {
        // Do nothing.
      }
    }
    return $entityData;
  }

  /**
   * {@inheritdoc}
   */
  public function getNormalizedEntityLanguage(EntityInterface $entity, bool $useLockedLanguages = TRUE): LanguageInterface {
    // Configurable languages are the normal ones (en, es, fr, etc).
    // Locked languages are LanguageInterface::LANGCODE_NOT_SPECIFIED (und) and
    // LanguageInterface::LANGCODE_NOT_APPLICABLE (zxx).
    $validLanguages = $this->languageManager->getLanguages($useLockedLanguages ? LanguageInterface::STATE_ALL : LanguageInterface::STATE_CONFIGURABLE);
    $entityLanguage = $entity->language();
    $entityLangcode = $entityLanguage->getId();
    if (isset($validLanguages[$entityLangcode])) {
      // Entity's language is among valid languages, so use it.
      return $entityLanguage;
    }

    // Entity's language is something else, so use the default one.
    // That "something else" is other languages like
    // LanguageInterface::LANGCODE_SYSTEM, LanguageInterface::LANGCODE_DEFAULT,
    // LanguageInterface::LANGCODE_SITE_DEFAULT, etc.
    // Depending on $useLockedLanguages, it can be also a locked language (see
    // above).
    return $this->languageManager->getDefaultLanguage();
  }

}
