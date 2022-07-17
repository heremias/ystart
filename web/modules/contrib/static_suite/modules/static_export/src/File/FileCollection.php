<?php

namespace Drupal\static_export\File;

/**
 * A class for holding a set of files.
 */
class FileCollection {

  /**
   * A unique Id to identify which process created this FileCollection.
   *
   * @var string
   */
  protected $uniqueId;

  /**
   * An array of FileItem.
   *
   * @var FileItem[]
   */
  protected $fileItems = [];

  /**
   * FileCollection constructor.
   *
   * @param string $uniqueId
   *   A unique id.
   */
  public function __construct(string $uniqueId) {
    $this->uniqueId = $uniqueId;
  }

  /**
   * Get unique id.
   *
   * @return string
   *   A unique id.
   */
  public function uniqueId(): string {
    return $this->uniqueId;
  }

  /**
   * Get all FileItems.
   *
   * @return \Drupal\static_export\File\FileItem[]
   *   An array of FileItem.
   */
  public function getFileItems(): array {
    return $this->fileItems;
  }

  /**
   * Get all executed FileItems.
   *
   * @return \Drupal\static_export\File\FileItem[]
   *   An array of FileItem.
   */
  public function getExecutedFileItems(): array {
    $executedItems = [];
    foreach ($this->fileItems as $fileItem) {
      if ($fileItem->isExecuted()) {
        $executedItems[] = $fileItem;
      }
    }
    return $executedItems;
  }

  /**
   * Get all executed FileItems paths, avoiding duplicates.
   *
   * @return string[]
   *   An array of file paths.
   */
  public function getExecutedFileItemPaths(): array {
    $executedFileItemPaths = [];
    $executedItems = $this->getExecutedFileItems();
    foreach ($executedItems as $item) {
      $executedFileItemPaths[$item->getFilePath()] = TRUE;
    }
    return array_keys($executedFileItemPaths);
  }

  /**
   * Set all FileItems.
   *
   * @param \Drupal\static_export\File\FileItem[] $fileItems
   *   An array of FileItem.
   */
  public function setFileItems(array $fileItems) {
    $this->fileItems = $fileItems;
  }

  /**
   * Get first FileItem.
   *
   * @return \Drupal\static_export\File\FileItem|null
   *   First FileItem.
   */
  public function getFirstFileItem(): ?FileItem {
    if (isset($this->fileItems[0])) {
      return $this->fileItems[0];
    }
    return NULL;
  }

  /**
   * Set an individual FileItem.
   *
   * @param \Drupal\static_export\File\FileItem $fileItem
   *   An individual FileItem.
   */
  public function addFileItem(FileItem $fileItem) {
    $this->fileItems[] = $fileItem;
  }

  /**
   * Returns the size of the collection.
   *
   * @return int
   *   The size of the collection.
   */
  public function size() {
    return count($this->fileItems);
  }

  /**
   * Tells if this FileCollection is empty.
   *
   * @return bool
   *   True if empty
   */
  public function isEmpty() {
    return $this->size() == 0 ? TRUE : FALSE;
  }

  /**
   * Merges a FileCollection with this one.
   *
   * @param FileCollection $anotherFileCollection
   *   Another FileCollection.
   */
  public function merge(FileCollection $anotherFileCollection) {
    $otherItems = $anotherFileCollection->getFileItems();
    foreach ($otherItems as $otherItem) {
      $this->addFileItem($otherItem);
    }
  }

  /**
   * Merges an array of fileCollections with this one.
   *
   * @param FileCollection[] $otherFileCollections
   *   An array of another fileCollections.
   */
  public function mergeMultiple(array $otherFileCollections) {
    foreach ($otherFileCollections as $otherFileCollection) {
      $this->merge($otherFileCollection);
    }
  }

  /**
   * Check if any file item has been executed (saved to the data-dir).
   *
   * @return bool
   *   True if any of them has been executed.
   */
  public function isAnyFileItemExecuted() {
    foreach ($this->fileItems as $item) {
      if ($item->isExecuted()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Check if this FileCollection affects PREVIEW data.
   *
   * Entities that are an instance of EditorialContentEntityBase can have an
   * status and be published/unpublished. If an entity that is not published
   * is updated, there is no need to run a LIVE build, since no published (LIVE)
   * data has changed. If an entity was published, and then is unpublished (or
   * the other way round), then there is a change to LIVE data.
   *
   * This method checks all FileItems inside it and tells if any of them affects
   * LIVE or PREVIEW data.
   *
   * @return bool
   *   True if it affects PREVIEW data.
   */
  public function isPreview(): bool {
    foreach ($this->getFileItems() as $fileItem) {
      if (!$fileItem->isPreview()) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   *
   */
  public function getBenchmark() {
    $benchmark = 0;
    foreach ($this->getFileItems() as $fileItem) {
      $benchmark += $fileItem->getBenchmark();
    }
    return $benchmark;
  }

}
