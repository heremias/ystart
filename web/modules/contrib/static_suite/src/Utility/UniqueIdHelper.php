<?php

namespace Drupal\static_suite\Utility;

use DateTime;
use DateTimeZone;
use Drupal\static_suite\StaticSuiteException;
use Exception;
use Throwable;

/**
 * Helper utilities for unique IDs.
 */
class UniqueIdHelper implements UniqueIdHelperInterface {

  /**
   * Static Export misc utilities.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected $staticSuiteUtils;

  /**
   * A unique ID.
   *
   * @var string
   */
  protected $uniqueId;

  /**
   * UniqueIdHelper constructor.
   *
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $static_suite_utils
   *   Static Export misc utilities.
   */
  public function __construct(StaticSuiteUtilsInterface $static_suite_utils) {
    $this->staticSuiteUtils = $static_suite_utils;
  }

  /**
   * {@inheritdoc}
   */
  public function getUniqueId(): string {
    if (!$this->uniqueId) {
      $this->uniqueId = $this->generateUniqueId();
    }
    return $this->uniqueId;
  }

  /**
   * {@inheritdoc}
   */
  public function generateUniqueId(): string {
    $microDate = $this->staticSuiteUtils->getFormattedMicroDate("Y-m-d_H-i-s.u");
    return $microDate . '__' . random_int(1000, 9999);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultUniqueId(): string {
    return "1970-01-01_12-00-00.000000__0000";
  }

  /**
   * {@inheritdoc}
   */
  public function isUniqueId($uniqueId): bool {
    // Check format.
    if (!preg_match("/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.\d{6}__\d{4}$/", $uniqueId)) {
      return FALSE;
    }

    // Do a smarter check. Try to get a date from it.
    try {
      if ($this->getDateFromUniqueId($uniqueId)) {
        return TRUE;
      }
    }
    catch (Throwable $e) {
      // Do nothing.
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDateFromUniqueId(string $uniqueId): DateTime {
    $microDate = substr($uniqueId, 0, 26);
    $microDate = preg_replace("/_(\d{2})-(\d{2})-(\d{2})/", " \\1:\\2:\\3", $microDate);
    try {
      $dateTime = new DateTime($microDate, new DateTimeZone('UTC'));
    }
    catch (Exception $e) {
      throw new StaticSuiteException('Unable to get date from unique id: ' . $uniqueId);
    }
    return $dateTime;
  }

}
