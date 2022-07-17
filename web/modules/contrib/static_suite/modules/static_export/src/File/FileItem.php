<?php

namespace Drupal\static_export\File;

/**
 * A class for holding all data for a file.
 */
class FileItem {

  public const OPERATION_WRITE = "write";

  public const OPERATION_DELETE = "delete";

  /**
   * Operation to be done.
   *
   * @var string
   */
  protected $operation;

  /**
   * Flag to tell if the above operation has been done.
   *
   * @var bool
   */
  protected $executed;

  /**
   * Flag to tell if this item is published.
   *
   * Applies only to instances of EditorialContentEntityBase. Other entities
   * are considered always published.
   *
   * @var int
   */
  protected $currentStatus = 1;

  /**
   * Flag to tell if this item was published.
   *
   * Applies only to instances of EditorialContentEntityBase. Other entities
   * are considered always published.
   *
   * @var int
   */
  protected $oldStatus = 1;

  /**
   * Absolute path for the file to be saved.
   *
   * @var string
   */
  protected $filePath;

  /**
   * Data to be saved on above $filePath.
   *
   * @var string
   */
  protected $fileContents;

  /**
   * A machine string that identifies the file (an entity id, etc)
   *
   * @var string
   */
  protected $id;

  /**
   * A human string that identifies the file (an entity title, etc)
   *
   * @var string
   */
  protected $label;

  /**
   * Elapsed time in seconds to generate this file.
   *
   * @var string
   */
  protected $benchmark;

  /**
   * FileItem constructor.
   *
   * @param string $operation
   *   Operation type.
   * @param int $currentStatus
   *   Item's current status.
   * @param int $oldStatus
   *   Item's old status.
   * @param string $filePath
   *   Absolute path for the file to be saved.
   * @param string $fileContents
   *   Data to be saved on above $filePath.
   * @param string $identifier
   *   A machine string that identifies the file (an entity id, etc).
   * @param string $label
   *   A human-readable string that identifies the file (an entity title, etc).
   * @param float $benchmark
   *   Elapsed time in seconds to generate this file.
   */
  public function __construct(string $operation, int $currentStatus, int $oldStatus, string $filePath, string $fileContents, string $identifier, string $label, float $benchmark) {
    if ($operation !== self::OPERATION_WRITE && $operation !== self::OPERATION_DELETE) {
      $operation = self::OPERATION_WRITE;
    }
    $this->operation = $operation;
    $this->currentStatus = $currentStatus;
    $this->oldStatus = $oldStatus;
    $this->filePath = $filePath;
    $this->fileContents = $fileContents;
    $this->id = $identifier;
    $this->label = $label;
    $this->benchmark = $benchmark;
  }

  /**
   * Get operation.
   *
   * @return string
   *   Operation.
   */
  public function getOperation(): string {
    return $this->operation;
  }

  /**
   * Set executed.
   *
   * @param bool $executed
   *   Executed.
   */
  public function setExecuted(bool $executed): void {
    $this->executed = $executed;
  }

  /**
   * Get executed flag.
   *
   * @return bool
   *   Executed flag.
   */
  public function isExecuted(): bool {
    return $this->executed;
  }

  /**
   * Get item current status.
   *
   * @return int
   *   1 if it's published, 0 otherwise.
   */
  public function getCurrentStatus(): int {
    return $this->currentStatus;
  }

  /**
   * Get item old status.
   *
   * @return int
   *   1 if it was published, 0 otherwise.
   */
  public function getOldStatus(): int {
    return $this->oldStatus;
  }

  /**
   * Tells whether this file affects PREVIEW data.
   *
   * Entities that are an instance of EditorialContentEntityBase can have an
   * status and be published/unpublished. If an entity that is not published
   * is updated, there is no need to run a LIVE build, since no published (LIVE)
   * data has changed. If an entity was published, and then is unpublished (or
   * the other way round), then there is a change to LIVE data.
   *
   * This method tells if this FileItem affects PREVIEW data.
   *
   * @return bool
   *   True if it affects PREVIEW data.
   */
  public function isPreview(): bool {
    return $this->getCurrentStatus() === 0 && $this->getOldStatus() === 0;
  }

  /**
   * Get filePath.
   *
   * @return string
   *   The filePath.
   */
  public function getFilePath(): string {
    return $this->filePath;
  }

  /**
   * Get fileContents.
   *
   * @return string
   *   FileContents.
   */
  public function getFileContents(): string {
    return $this->fileContents;
  }

  /**
   * Get id.
   *
   * @return string
   *   The identifier.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Get label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * Get benchmark.
   *
   * @return float
   *   The benchmark.
   */
  public function getBenchmark(): float {
    return $this->benchmark;
  }

}
