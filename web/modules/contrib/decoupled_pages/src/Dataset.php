<?php

namespace Drupal\decoupled_pages;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Represents the data attributes that will be added to the root element.
 */
final class Dataset extends \ArrayIterator implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  /**
   * Dataset constructor.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   The cacheability of the dataset.
   * @param array $data
   *   The dataset.
   */
  public function __construct(CacheableMetadata $cacheability, array $data) {
    $this->setCacheability($cacheability);
    parent::__construct($data);
  }

  /**
   * Creates a new dataset that will be cached permanently.
   *
   * @param array $data
   *   The dataset.
   *
   * @return \Drupal\decoupled_pages\Dataset
   *   The dataset object.
   */
  public static function cachePermanent(array $data) {
    return new static(new CacheableMetadata(), $data);
  }

  /**
   * Creates a new dataset with cacheability metadata.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   The cacheability of the dataset.
   * @param array $data
   *   The dataset.
   *
   * @return \Drupal\decoupled_pages\Dataset
   *   The dataset object.
   */
  public static function cacheVariable(CacheableMetadata $cacheability, array $data) {
    return new static($cacheability, $data);
  }

  /**
   * Merges two or more data attribute objects.
   *
   * @param \Drupal\decoupled_pages\Dataset $a
   *   A dataset to merge with another dataset.
   * @param \Drupal\decoupled_pages\Dataset $b
   *   A dataset to be merged with the first argument. Data attributes in $b
   *   with the a name already present in dataset $a will override attributes in
   *   dataset $a.
   *
   * @return \Drupal\decoupled_pages\Dataset
   *   A new dataset with the combined cacheability of all arguments and all
   *   values.
   */
  public static function merge(Dataset $a, Dataset $b): Dataset {
    return new static(
      CacheableMetadata::createFromObject($a)->addCacheableDependency($b),
      array_merge(iterator_to_array($a), iterator_to_array($b))
    );
  }

}
