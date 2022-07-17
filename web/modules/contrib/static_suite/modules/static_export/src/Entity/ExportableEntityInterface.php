<?php

namespace Drupal\static_export\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;

/**
 * Provides an interface defining an Exportable entity.
 */
interface ExportableEntityInterface extends ConfigEntityInterface {

  public const ENTITY_ID_TOKEN = '[entity:id]';

  /**
   * Get the entity type id used in this exportable entity.
   *
   * Its name is getEntityTypeIdString() to avoid conflicting with
   * EntityInterface::getEntityTypeId()
   *
   * @return string
   *   Entity type id.
   */
  public function getEntityTypeIdString(): string;

  /**
   * Get the bundle used in this exportable entity.
   *
   * @return string
   *   Bundle.
   */
  public function getBundle(): string;

  /**
   * Get data resolver.
   *
   * @return string
   *   The id of the data resolver plugin.
   */
  public function getDataResolver(): ?string;

  /**
   * Set data resolver.
   *
   * @param string $dataResolver
   *   The id of the data resolver plugin.
   */
  public function setDataResolver(string $dataResolver): void;

  /**
   * Get export directory.
   *
   * Relative to base dir (entity, config, etc) inside data dir, and without
   * any reference to languages.
   *
   * @return string
   *   The directory where exported entity data is saved.
   * @see ExpotableEntity::getDirectory
   */
  public function getDirectory(): string;

  /**
   * Get export filename.
   *
   * It must contain the token self::ENTITY_ID_TOKEN that is later replaced by
   * the entity id. Using a token ensures that filename contains the entity id,
   * offering flexibility at the same time.
   *
   * @return string
   *   The export filename containing an entity id token.
   */
  public function getFilename(): string;

  /**
   * Get export extension.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to get extension from.
   *
   * @return string
   *   The export extension
   */
  public function getExtension(EntityInterface $entity): string;

  /**
   * Gets the output formatter manager.
   *
   * Since this is a special class for config entities (@ConfigEntityType),
   * dependencies can't be easily provided using Symfony's Dependency
   * Injection. Drupal Core uses a different strategy for such cases, where
   * dependencies are returned by a wrapper method like this one.
   *
   * @return \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   *   The output format manager.
   */
  public function outputFormatterManager(): OutputFormatterPluginManagerInterface;

  /**
   * Gets the data resolver manager.
   *
   * Since this is a special class for config entities (@ConfigEntityType),
   * dependencies can't be easily provided using Symfony's Dependency
   * Injection. Drupal Core uses a different strategy for such cases, where
   * dependencies are returned by a wrapper method like this one.
   *
   * @return \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface
   *   The data resolver manager.
   */
  public function dataResolverManager(): DataResolverPluginManagerInterface;

  /**
   * Get export format.
   *
   * @return string|null
   *   The export format.
   */
  public function getFormat(): ?string;

  /**
   * Set export format.
   *
   * @param string $format
   */
  public function setFormat(string $format): void;

  /**
   * Tells whether it should export other entities where an entity appears.
   *
   * @return bool
   *   True or false.
   */
  public function getExportReferencingEntities(): bool;

  /**
   * Sets whether it should export other entities where an entity appears.
   *
   * @param bool $flag
   *   A boolean flag.
   */
  public function setExportReferencingEntities(bool $flag): void;

  /**
   * Get recursion level.
   *
   * @return int|null
   *   The recursion level, from 0 (infinite) to any positive number.
   */
  public function getRecursionLevel(): ?int;

  /**
   * Set recursion level.
   *
   * @param int $recursionLevel
   */
  public function setRecursionLevel(int $recursionLevel): void;

  /**
   * Tells whether it should export data when a CRUD operation happens on CLI.
   *
   * @return bool
   *   True or false.
   */
  public function getExportWhenCrudHappensOnCli(): bool;

  /**
   * Sets whether it should export data when a CRUD operation happens on CLI.
   *
   * @param bool $flag
   *   A boolean flag.
   */
  public function setExportWhenCrudHappensOnCli(bool $flag): void;

  /**
   * Tells whether to request a build when a CRUD operation exports data on CLI.
   *
   * @return bool
   *   True or false.
   */
  public function getRequestBuildWhenCrudExportsOnCli(): bool;

  /**
   * Sets whether to request a build when a CRUD operation exports data on CLI.
   *
   * @param bool $flag
   *   A boolean flag.
   */
  public function setRequestBuildWhenCrudExportsOnCli(bool $flag): void;

  /**
   * Tells whether this entity is a statified page.
   *
   * Pages already statified by the Static Builder of your choice are not
   * served by Drupal anymore. This option changes the content type's edit
   * form and the way Drupal renders and previews your content.
   *
   * This option must be enabled so any Static Preview module can work once you
   * have finished migrating a node/taxonomy term, etc to its static version.
   *
   * @return bool
   *   True or false.
   */
  public function getIsStatifiedPage(): bool;

  /**
   * Sets whether to this entity is a statified page.
   *
   * @param bool $flag
   *   A boolean flag.
   */
  public function setIsStatifiedPage(bool $flag): void;

}
