<?php

namespace Drupal\static_suite\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * An interface for a set of useful utils related to entities.
 *
 * Most methods could be declared as static, but that would make impossible
 * to override this service or decorate it.
 */
interface EntityUtilsInterface {

  /**
   * Load an entity.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $entityId
   *   The entity id.
   * @param string|null $langcode
   *   Optional language code of the entity. If not provided, it loads the
   *   entity with its original language, and not the default language.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Loaded entity.
   */
  public function loadEntity(string $entityTypeId, string $entityId, string $langcode = NULL): ?EntityInterface;

  /**
   * Get entity ids.
   *
   * @param string $entityTypeId
   *   Entity type id.
   * @param array $options
   *   An associative array with query options:
   *   - bundle: int, entity bundle id
   *   - status: int, entity status, 0 (unpublished) or 1 (published)
   *   - conditions: array of associative arrays
   *     - field: string, name of a field
   *     - value: mixed, the value for field
   *     - operator: same values as
   *   Drupal\Core\Entity\Query\QueryInterface::condition()
   *   - sort: associative array
   *     - field: string, name of a field
   *     - direction: string, ASC or DESC
   *   - range: associative array
   *     - start: int, range start
   *     - length: int, range length.
   *
   * @return array
   *   Entity ids
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   */
  public function getEntityIds(string $entityTypeId, $options = []): array;

  /**
   * Returns the languages the config entity is translated to.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $configEntity
   *   The config entity.
   * @param bool $include_default
   *   (optional) Whether the default language should be included. Defaults to
   *   TRUE.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]
   *   An associative array of language objects, keyed by language codes.
   */
  public function getConfigEntityTranslationLanguages(ConfigEntityInterface $configEntity, bool $include_default = TRUE): array;

  /**
   * Check if entity status has changed.
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $entity
   *   The Entity to be checked.
   *
   * @return bool
   *   True if it has changed
   */
  public function hasStatusChanged(EditorialContentEntityBase $entity): bool;

  /**
   * Given a path or alias, return an array of data about the entity.
   *
   * @param string $pagePath
   *   Page path (or alias).
   * @param string|null $langcode
   *   An optional language code to look up the path in.
   *
   * @return array|null
   *   Array with two keys:
   *   1) 'entityTypeId': entity type id
   *   2) 'entityId': entity id
   *   If no entity can be found, null is returned.
   */
  public function getEntityDataByPagePath(string $pagePath, string $langcode = NULL): ?array;

  /**
   * Check entity's language and return a normalized language.
   *
   * "Normalized" language = LanguageInterface::STATE_CONFIGURABLE ||
   *   LanguageInterface::STATE_LOCKED.
   *
   * An entity can use a language that is not among the configurable or locked
   * ones (e.g.- LanguageInterface::LANGCODE_SYSTEM,
   * LanguageInterface::LANGCODE_DEFAULT, etc). In such cases, we filter out
   * those languages and use the default one.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to get its language.
   *
   * @param bool $useLockedLanguages
   *   If true, and a locked language is found, it returns that language.
   *   If false, and a locked language is found, it returns the default one.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   Normalized language
   */
  public function getNormalizedEntityLanguage(EntityInterface $entity, bool $useLockedLanguages = TRUE): LanguageInterface;

}
