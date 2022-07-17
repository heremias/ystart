<?php

namespace Drupal\static_export\File;

/**
 * A class for holding a group of FileCollection.
 */
class FileCollectionGroup {

  /**
   * An array of FileCollection.
   *
   * @var \Drupal\static_export\File\FileCollection[]
   */
  protected $fileCollections = [];

  /**
   * FileCollectionGroup constructor.
   *
   * @param FileCollection $fileCollection
   *   A FileCollection.
   */
  public function __construct(FileCollection $fileCollection = NULL) {
    if ($fileCollection) {
      $this->addFileCollection($fileCollection);
    }
  }

  /**
   * Get all FileCollections.
   *
   * @return \Drupal\static_export\File\FileCollection[]
   *   An array of FileCollection.
   */
  public function getFileCollections(): array {
    return $this->fileCollections;
  }

  /**
   * Set all FileCollections.
   *
   * @param \Drupal\static_export\File\FileCollection[] $fileCollections
   *   An array of FileCollection.
   */
  public function setFileCollections(array $fileCollections) {
    $this->fileCollections = $fileCollections;
  }

  /**
   * Add an individual FileCollection.
   *
   * @param \Drupal\static_export\File\FileCollection $fileCollection
   *   An individual FileCollection.
   */
  public function addFileCollection(FileCollection $fileCollection) {
    $this->fileCollections[] = $fileCollection;
  }

  /**
   * Adds multiple FileCollections.
   *
   * @param \Drupal\static_export\File\FileCollection[] $fileCollections
   *   An array of FileCollection.
   */
  public function addFileCollections(array $fileCollections) {
    foreach ($fileCollections as $fileCollection) {
      $this->addFileCollection($fileCollection);
    }
  }

  /**
   * Get first FileCollection.
   *
   * @return \Drupal\static_export\File\FileCollection|null
   *   First FileCollection.
   */
  public function getFirstFileCollection(): ?FileCollection {
    if (isset($this->fileCollections[0])) {
      return $this->fileCollections[0];
    }
    return NULL;
  }

  /**
   * Returns the size of the group.
   *
   * @return int
   *   The size of the collection.
   */
  public function size() {
    return count($this->fileCollections);
  }

  /**
   * Tells if this group is empty.
   *
   * @return bool
   *   True if empty
   */
  public function isEmpty() {
    return $this->size() == 0 ? TRUE : FALSE;
  }

  /**
   * Check if FileCollection[] items have been executed.
   *
   * Check if any of the FileCollection inside a FileCollection[] has been
   * executed (AKA saved to data-dir).
   *
   * @return bool
   *   True if any of them has been executed.
   */
  public function isAnyFileCollectionExecuted() {
    if ($this->isEmpty()) {
      return FALSE;
    }
    foreach ($this->fileCollections as $fileCollection) {
      if ($fileCollection->isAnyFileItemExecuted()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get executed items from all file collections.
   *
   * @return \Drupal\static_export\File\FileItem[]
   *   Array of FileItem
   */
  public function getExecutedFileItemsFromAllFileCollections() {
    $executedItems = [];
    if ($this->isEmpty()) {
      return [];
    }
    foreach ($this->fileCollections as $fileCollection) {
      $executedItems = array_merge($executedItems, $fileCollection->getExecutedFileItems());
    }
    return $executedItems;
  }

  /**
   * Get file paths from all file collections.
   *
   * @return \Drupal\static_export\File\FileItem[]
   *   Array of FileItem
   */
  public function getExecutedFilePathsFromAllFileCollections() {
    $executedFilePaths = [];
    $executedFileItems = $this->getExecutedFileItemsFromAllFileCollections();
    foreach ($executedFileItems as $executedFileItem) {
      $executedFilePaths[$executedFileItem->getFilePath()] = TRUE;
    }
    return array_keys($executedFilePaths);
  }

  /**
   * Get FileCollections newest item.
   *
   * @return \Drupal\static_export\File\FileCollection|null
   *   A FileCollection or NULL if nothing found.
   */
  public function getNewestFileCollection() {
    if ($this->isEmpty()) {
      return NULL;
    }
    $itemsByUniqueId = [];
    foreach ($this->fileCollections as $fileCollection) {
      $itemsByUniqueId[$fileCollection->uniqueId()] = $fileCollection;
    }
    krsort($itemsByUniqueId);
    return array_shift($itemsByUniqueId);
  }

}
