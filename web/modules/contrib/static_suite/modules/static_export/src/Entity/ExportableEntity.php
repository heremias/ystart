<?php

namespace Drupal\static_export\Entity;

use Drupal;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginInterface;

/**
 * Defines the Exportable Entity.
 *
 * @ConfigEntityType(
 *   id = "exportable_entity",
 *   label = @Translation("Exportable entity"),
 *   handlers = {
 *     "list_builder" =
 *   "Drupal\static_export\Controller\ExportableEntityListBuilder",
 *     "form" = {
 *       "add" = "Drupal\static_export\Form\ExportableEntityForm",
 *       "edit" = "Drupal\static_export\Form\ExportableEntityForm",
 *       "delete" = "Drupal\static_export\Form\ExportableEntityDeleteForm",
 *       "enable" = "Drupal\static_export\Form\ExportableEntityEnableForm",
 *       "disable" = "Drupal\static_export\Form\ExportableEntityDisableForm"
 *     }
 *   },
 *   config_prefix = "exportable_entity",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "data_resolver",
 *     "format",
 *     "export_referencing_entities",
 *     "recursion_level",
 *     "export_when_crud_happens_on_cli",
 *     "request_build_when_crud_exports_on_cli",
 *     "is_statified_page"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/static/export/entity/{exportable_entity}",
 *     "delete-form" =
 *   "/admin/config/static/export/entity/{exportable_entity}/delete",
 *     "enable" =
 *   "/admin/config/static/export/entity/{exportable_entity}/enable",
 *     "disable" =
 *   "/admin/config/static/export/entity/{exportable_entity}/disable"
 *   }
 * )
 */
class ExportableEntity extends ConfigEntityBase implements ExportableEntityInterface {

  /**
   * Entity ID.
   *
   * @var string
   */
  protected $id;

  /**
   * Entity label.
   *
   * @var string
   */
  protected $label;

  /**
   * The id of the data resolver plugin.
   *
   * @var string
   */
  protected $data_resolver;

  /**
   * The export format.
   *
   * @var string
   */
  protected $format;

  /**
   * A flag to tell if it should export other entities where an entity appears.
   *
   * @var bool
   */
  protected $export_referencing_entities = FALSE;

  /**
   * The recursion level.
   *
   * @var int
   */
  protected $recursion_level = 1;

  /**
   * A flag to tell whether it should export data when a CRUD operation happens
   * on CLI.
   *
   * @var bool
   */
  protected $export_when_crud_happens_on_cli = TRUE;

  /**
   * A flag to tell whether to request a build when a CRUD operation exports
   * data on CLI.
   *
   * @var bool
   */
  protected $request_build_when_crud_exports_on_cli = FALSE;

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
   * @var bool
   */
  protected $is_statified_page = FALSE;

  /**
   * Output formatter manager.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected $outputFormatterManager;

  /**
   * Data resolver manager.
   *
   * @var \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface
   */
  protected $dataResolverPluginManager;

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeIdString(): string {
    return explode('.', $this->id())[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle(): string {
    return explode('.', $this->id())[1];
  }

  /**
   * {@inheritdoc}
   */
  public function getDataResolver(): ?string {
    return $this->data_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function setDataResolver(string $dataResolver): void {
    $this->data_resolver = $dataResolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectory(): string {
    return $this->getEntityTypeIdString() . '/' . $this->getBundle() . EntityExporterPluginInterface::OPTIONAL_SUB_DIR_TOKEN;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(): string {
    return EntityExporterPluginInterface::ENTITY_ID_TOKEN;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtension(EntityInterface $entity): string {
    $definitions = $this->outputFormatterManager()->getDefinitions();
    return !empty($definitions[$this->format]) ? $definitions[$this->format]['extension'] : $this->format;
  }

  /**
   * {@inheritdoc}
   */
  public function outputFormatterManager(): OutputFormatterPluginManagerInterface {
    if (!$this->outputFormatterManager) {
      $this->outputFormatterManager = Drupal::service('plugin.manager.static_output_formatter');
    }

    return $this->outputFormatterManager;
  }

  /**
   * {@inheritdoc}
   */
  public function dataResolverManager(): DataResolverPluginManagerInterface {
    if (!$this->dataResolverPluginManager) {
      $this->dataResolverPluginManager = Drupal::service('plugin.manager.static_data_resolver');
    }

    return $this->dataResolverPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormat(): ?string {
    return $this->format;
  }

  /**
   * {@inheritdoc}
   */
  public function setFormat(string $format): void {
    $this->format = $format;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportReferencingEntities(): bool {
    return $this->export_referencing_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function setExportReferencingEntities(bool $flag): void {
    $this->export_referencing_entities = $flag;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecursionLevel(): ?int {
    return $this->recursion_level;
  }

  /**
   * {@inheritdoc}
   */
  public function setRecursionLevel(int $recursionLevel): void {
    $this->recursion_level = $recursionLevel;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportWhenCrudHappensOnCli(): bool {
    return $this->export_when_crud_happens_on_cli;
  }

  /**
   * {@inheritdoc}
   */
  public function setExportWhenCrudHappensOnCli(bool $flag): void {
    $this->export_when_crud_happens_on_cli = $flag;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestBuildWhenCrudExportsOnCli(): bool {
    return $this->request_build_when_crud_exports_on_cli;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestBuildWhenCrudExportsOnCli(bool $flag): void {
    $this->request_build_when_crud_exports_on_cli = $flag;
  }

  /**
   * {@inheritdoc}
   */
  public function getIsStatifiedPage(): bool {
    return $this->is_statified_page;
  }

  /**
   * {@inheritdoc}
   */
  public function setIsStatifiedPage(bool $flag): void {
    $this->is_statified_page = $flag;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    $resolver = $this->getDataResolver();
    $format = $this->getFormat();
    $dataResolverPluginManager = $this->dataResolverManager();

    // Add data resolver dependency.
    if ($resolver) {
      $dataResolverDefinition = $dataResolverPluginManager->getDefinition($resolver);
      $this->addDependency('module', $dataResolverDefinition['provider']);
    }

    // Add formatter dependency.
    $dataResolversThatExportRawData = $dataResolverPluginManager->getDataResolverIdsThatExportRawData();
    if ($format && in_array($resolver, $dataResolversThatExportRawData, TRUE)) {
      $outputFormatterPluginManager = $this->outputFormatterManager();
      $outputFormatterDefinition = $outputFormatterPluginManager->getDefinition($this->getFormat());
      $this->addDependency('module', $outputFormatterDefinition['provider']);
    }

    return $this;
  }

}
