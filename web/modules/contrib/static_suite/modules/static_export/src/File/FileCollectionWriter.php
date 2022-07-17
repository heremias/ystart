<?php

namespace Drupal\static_export\File;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\static_export\Event\StaticExportEvent;
use Drupal\static_export\Event\StaticExportEvents;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_suite\Lock\LockHelperInterface;
use Drupal\static_suite\StaticSuiteException;
use Drupal\static_suite\StaticSuiteUserException;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;
use Drupal\static_suite\Utility\UniqueIdHelperInterface;
use Throwable;

/**
 * A class for handling the writing of data to disk, using atomic operations.
 *
 * Given the latency of processors and disk drives, it's impossible to ensure
 * a proper lock system with files, so we use semaphores.
 */
class FileCollectionWriter {

  /**
   * The number of seconds to wait for acquiring a lock.
   *
   * It should be the same or greater than
   * QUEUE_INSERTION_MAX_SECONDS, because we have to wait, in order, for all
   * queue items to finish. If any of the items is taking some time to finish,
   * we can not consider the lock stale.
   *
   * @var int
   */
  const LOCK_ACQUIRE_MAX_SECONDS = 60;

  /**
   * The number of seconds that a queue insertion may take.
   *
   * This is the time a FileCollection could take to be created. It's usually
   * under 10 seconds, but could span to several minutes in the case of "really
   * big" FileCollectionGroup. We should implement a way to bypass commit order
   * control when we encounter that problem.
   *
   * @var int
   */
  const QUEUE_INSERTION_MAX_SECONDS = 20;

  /**
   * The number of seconds to wait before checking for queue insertions again.
   *
   * @var int
   */
  const QUEUE_INSERTION_CHECK_FREQUENCY = 1;

  /**
   * The name of the dir for holding queue items.
   *
   * @var string
   */
  const QUEUE_DIR = 'queue';

  /**
   * The extension for pending elements in the queue.
   *
   * @var string
   */
  const QUEUE_PENDING_FILE_EXTENSION = '.pending';

  /**
   * The name of the file for logging commits.
   *
   * @var string
   */
  const COMMIT_LOG_FILE = 'commit.log';

  /**
   * The name of the file for logging executed real changes on data-dir.
   *
   * @var string
   */
  const LOCK_EXECUTED_LOG_FILE = 'lock-executed.log';

  /**
   * The name of the file for saving last lock committed unique id.
   *
   * @var string
   */
  const LAST_LOCK_COMMITTED_UNIQUE_ID_FILE = 'last-lock-committed.unique-id';

  /**
   * The name of the file for saving the last lock executed live unique id.
   *
   * This is where export processes save their unique id when they make real
   * changes on data-dir, AND those changes are triggering a new live build.
   * For example, all published contents, config, locales, menus, etc will
   * touch this file.
   *
   * @var string
   */
  const LAST_LOCK_EXECUTED_LIVE_UNIQUE_ID_FILE = 'last-lock-executed.live.unique-id';

  /**
   * The name of the file for saving the last lock executed preview unique id.
   *
   * This is where export processes save their unique id when they make real
   * changes on data-dir, BUT those changes are not triggering a new live build
   * but a preview build. This only happens when we are saving a
   * FileCollection which contains only one FileItem, that FileItem is not
   * published, and no change in publish status happened.
   * For example, all unpublished contents, when not changing their status, will
   * touch this file.
   *
   * @var string
   */
  const LAST_LOCK_EXECUTED_PREVIEW_UNIQUE_ID_FILE = 'last-lock-executed.preview.unique-id';

  /**
   * The exporter that controls this writer.
   *
   * @var \Drupal\static_export\Exporter\ExporterPluginInterface
   */
  protected $exporter;

  /**
   * The lock helper from Static Suite.
   *
   * @var \Drupal\static_suite\Lock\LockHelperInterface
   */
  protected $lockHelper;

  /**
   * The lock system.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * A FileCollectionGroup.
   *
   * @var \Drupal\static_export\File\FileCollectionGroup
   */
  protected $fileCollectionGroup;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Static Suite utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected $staticSuiteUtils;

  /**
   * Unique ID helper.
   *
   * @var \Drupal\static_suite\Utility\UniqueIdHelperInterface
   */
  protected $uniqueIdHelper;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * FileCollectionWriter constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param \Drupal\static_suite\Lock\LockHelperInterface $lockHelper
   *   The lock helper from Static Suite.
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $static_suite_utils
   *   Static Suite utils.
   * @param \Drupal\static_suite\Utility\UniqueIdHelperInterface $unique_id_helper
   *   The unique ID helper.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FileSystemInterface $fileSystem, LockHelperInterface $lockHelper, StaticSuiteUtilsInterface $static_suite_utils, UniqueIdHelperInterface $unique_id_helper) {
    $this->configFactory = $config_factory;
    $this->fileSystem = $fileSystem;
    $this->lockHelper = $lockHelper;
    $this->lock = $this->lockHelper->getLock();
    $this->staticSuiteUtils = $static_suite_utils;
    $this->uniqueIdHelper = $unique_id_helper;
  }

  /**
   * Set exporter.
   *
   * @param \Drupal\static_export\Exporter\ExporterPluginInterface $exporter
   *   The exporter that controls this writer.
   */
  public function setExporter(ExporterPluginInterface $exporter) {
    $this->exporter = $exporter;
  }

  /**
   * Get unique id.
   *
   * @return string
   *   Unique id.
   */
  protected function uniqueId() {
    return $this->exporter->uniqueId();
  }

  /**
   * Get work dir.
   *
   * @return string
   *   The work dir.
   */
  protected function getWorkDir() {
    return $this->configFactory->get('static_export.settings')->get('work_dir');
  }

  /**
   * Entry point for saving a FileCollection.
   *
   * @param FileCollection $fileCollection
   *   FileCollection to be saved.
   *
   * @return FileCollectionGroup
   *   A FileCollectionGroup with stats.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function save(FileCollection $fileCollection) {
    // We assign it to a property to easily pass it to events.
    $this->fileCollectionGroup = new FileCollectionGroup();

    if ($this->exporter->isLock() === FALSE) {
      $singleFileCollection = $this->commitFileCollection($fileCollection);
      $this->fileCollectionGroup->addFileCollection($singleFileCollection);
    }
    else {
      // Honor the value defined in self::LOCK_ACQUIRE_MAX_SECONDS.
      // This way, Apache won't kill this process before finishing.
      if (!$this->staticSuiteUtils->isRunningOnCli()) {
        set_time_limit(self::LOCK_ACQUIRE_MAX_SECONDS);
      }

      // Finish pending insertion.
      $this->finishQueueInsertion($fileCollection);

      // Instead of waiting 30 seconds, wait 60 seconds to ensure all elements
      // in queue can be resolved (i.e.- a complex GraphQL query taking too long
      // to resolve, etc)
      $this->exporter->logMessage('[LOCK] Acquiring lock on "data dir"');
      if (!$this->lockHelper->acquireOrWait(ExporterPluginInterface::DATA_DIR_LOCK_NAME, 60, 60)) {
        throw new StaticSuiteUserException('Could not acquire lock on "data dir", timeout reached.');
      }
      try {
        $this->exporter->logMessage('[LOCK] Acquired lock on "data dir"');
        $this->fileCollectionGroup = $this->processQueue();
      }
      finally {
        $this->exporter->logMessage('[LOCK] Releasing lock on "data dir"');
        $this->lock->release(ExporterPluginInterface::DATA_DIR_LOCK_NAME);
        $this->exporter->logMessage('[LOCK] Lock on "data dir" released');
      }
    }

    return $this->fileCollectionGroup;
  }

  /**
   * Processes the queue.
   *
   * For each processed item, it saves it FileCollection in an array and return
   * it.
   *
   * @return FileCollectionGroup
   *   A FileCollectionGroup.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function processQueue() {
    $this->exporter->dispatchEvent(StaticExportEvents::WRITE_QUEUE_PROCESSING_START);

    $this->exporter->logMessage("[writer] Queue processing starts...");
    $fileCollectionGroup = new FileCollectionGroup();
    while ($queueItem = $this->pullItemFromQueue()) {
      if ($this->isQueueItemStale($queueItem)) {
        $this->exporter->logMessage("[writer] Queue item " . $queueItem['unique-id'] . " found stale. Deleting it...");
        $this->deleteFile($queueItem['file-path']);
        $this->exporter->logMessage("[writer] Queue item " . $queueItem['unique-id'] . " deleted.");
      }
      else {
        if ($queueItem['done']) {
          $this->exporter->logMessage("[writer] Queue item " . $queueItem['unique-id'] . " found. Processing it...");
          // If item is done, commit it.
          $queueItem = $this->commitQueueItem($queueItem);
          $this->deleteFile($queueItem['file-path']);
          $fileCollectionGroup->addFileCollection($queueItem['file-collection']);
          $this->exporter->logMessage("[writer] Queue item " . $queueItem['unique-id'] . " processed and deleted from queue.");
        }
        else {
          // If not, wait and pull the item again from the queue until it's
          // done or stale.
          $this->exporter->logMessage("[writer] Queue item " . $queueItem['unique-id'] . " found. Waiting a maximum of " . self::QUEUE_INSERTION_MAX_SECONDS . " seconds for it...");
          sleep(self::QUEUE_INSERTION_CHECK_FREQUENCY);
        }
      }
    }
    $this->exporter->logMessage("[writer] Queue processing done.");

    $event = $this->exporter->dispatchEvent(
      StaticExportEvents::WRITE_QUEUE_PROCESSING_END,
      [StaticExportEvent::EVENT_FILE_COLLECTION_GROUP => $fileCollectionGroup]
    );
    $fileCollectionGroup = $event->getFileCollectionGroup();

    return $fileCollectionGroup;
  }

  /**
   * Commit a queue item to data-dir.
   *
   * @param array $item
   *   An array containing a queue item data.
   *
   * @return array
   *   The committed queue item.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function commitQueueItem(array $item) {
    $item['file-collection'] = $this->commitFileCollection($item['file-collection']);
    return $item;
  }

  /**
   * Commit a FileCollection to data-dir.
   *
   * @param FileCollection $fileCollection
   *   A FileCollection.
   *
   * @return FileCollection
   *   The processed FileCollection.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function commitFileCollection(FileCollection $fileCollection) {
    $fileItems = $fileCollection->getFileItems();
    $this->exporter->logMessage("COMMIT START");
    $this->logCommitMessage("COMMIT START");
    foreach ($fileItems as $fileItem) {
      if ($fileItem->getOperation() === FileItem::OPERATION_DELETE) {
        $executed = $this->deleteFileItem($fileItem->getFilePath());
      }
      else {
        $executed = $this->saveFileItem($fileItem->getFilePath(), $fileItem->getFileContents());
      }
      $fileItem->setExecuted($executed);

      // Save commit log.
      $commitMessage = $fileCollection->uniqueId() . " ";
      $commitMessage .= sprintf("[%6s] ", $fileItem->getBenchmark() . "s");
      $commitMessage .= "[" . ($fileCollection->isPreview() ? 'preview' : 'live') . "] ";
      $commitMessage .= "[" . $fileItem->getOperation() . ($executed ? ': executed' : ': skipped') . "] ";
      $commitMessage .= "[ID: " . $fileItem->getId() . "] ";
      $commitMessage .= $fileItem->getLabel() . " | ";
      $commitMessage .= $fileItem->getFilePath();
      $this->logCommitMessage($commitMessage);
      $this->exporter->logMessage($commitMessage);
    }
    $this->logCommitMessage("COMMIT END\n");
    $this->exporter->logMessage("COMMIT END\n");

    // During a "no-lock operation", control files must not be updated, to not
    // interfere with other "lock operations". Hence, "no-lock operations" must
    // be carefully executed.
    if ($this->exporter->isLock()) {
      // Save last committed uniqueId.
      $this->saveLastLockCommittedUniqueId($fileCollection->uniqueId());

      // Save last executed uniqueId.
      if ($fileCollection->isAnyFileItemExecuted()) {
        $this->saveLastLockExecutedUniqueId($fileCollection);
        $this->saveLockExecutedLog($fileCollection);
      }
    }

    return $fileCollection;
  }

  /**
   * Saves a log of lock executed files.
   *
   * @param FileCollection $fileCollection
   *   A FileCollection.
   *
   * @return bool
   *   True on success, false otherwise
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function saveLockExecutedLog(FileCollection $fileCollection) {
    $fileItems = $fileCollection->getFileItems();
    foreach ($fileItems as $fileItem) {
      if ($fileItem->isExecuted()) {
        $logLine = $fileCollection->uniqueId() . " ";
        $logLine .= $fileItem->getOperation() . " ";
        $logLine .= "[ID: " . $fileItem->getId() . "] ";
        $logLine .= $fileItem->getLabel() . " | ";
        $logLine .= $fileItem->getFilePath();
        $logFile = $this->getWorkDir() . "/" . self::LOCK_EXECUTED_LOG_FILE;
        file_put_contents($logFile, "$logLine\n", FILE_APPEND | LOCK_EX);
      }
    }

    return TRUE;
  }

  /**
   * Delete a FileItem.
   *
   * @param string $filePath
   *   The file path.
   *
   * @return bool
   *   TRUE on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function deleteFileItem(string $filePath) {
    // Do nothing if $filePath does not exist.
    clearstatcache(TRUE, $filePath);
    if (!is_file($filePath)) {
      return FALSE;
    }
    return $this->deleteFile($filePath);
  }

  /**
   * Saves a FileItem.
   *
   * @param string $filePath
   *   The file path.
   * @param string $fileContents
   *   The file contents.
   *
   * @return bool
   *   TRUE on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function saveFileItem(string $filePath, string $fileContents) {
    // Do nothing if contents are the same.
    clearstatcache(TRUE, $filePath);
    if (!$this->exporter->isForceWrite() && is_file($filePath) && @file_get_contents($filePath) == $fileContents) {
      return FALSE;
    }

    // Write directly to $filePath. No need for temporary files, since we are
    // locking file operations.
    return $this->writeFile($filePath, $fileContents, TRUE, TRUE);
  }

  /**
   * Write a file.
   *
   * @param string $filePath
   *   The file path.
   * @param string $fileContents
   *   The file contents.
   * @param bool $overwrite
   *   Flag to allow overwriting other files.
   * @param bool $createDirs
   *   Flag to allow creation of needed dirs for $filePath.
   *
   * @return bool
   *   TRUE on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function writeFile(string $filePath, string $fileContents, bool $overwrite = FALSE, bool $createDirs = FALSE) {
    // Ensure proper paths.
    if (strpos($filePath, "..") !== FALSE) {
      throw new StaticSuiteException("File path can not contain '..': " . $filePath);
    }

    clearstatcache(TRUE, $filePath);
    if (is_dir($filePath) || is_link($filePath)) {
      throw new StaticSuiteException("Trying to write on a non-file path: " . $filePath);
    }

    clearstatcache(TRUE, $filePath);
    if (is_file($filePath) && !$overwrite) {
      throw new StaticSuiteException("Error while overwriting a file. Overwrite not allowed for: " . $filePath);
    }

    if ($createDirs) {
      // Prepare output dir.
      $writeDir = $this->fileSystem->dirname($filePath);
      clearstatcache(TRUE, $writeDir);
      if (!file_exists($writeDir)) {
        $mkdirResult = $this->fileSystem->mkdir($writeDir, 0777, TRUE);
        if ($mkdirResult === FALSE) {
          throw new StaticSuiteException("Error while creating dir: " . $writeDir);
        }
      }
    }

    $result = file_put_contents($filePath, $fileContents);
    if ($result === FALSE) {
      throw new StaticSuiteException("Error while writing to file: " . $filePath);
    }
    return TRUE;
  }

  /**
   * Delete a file.
   *
   * @param string $filePath
   *   The file path.
   *
   * @return bool
   *   TRUE on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function deleteFile(string $filePath) {
    // Ensure proper paths.
    if (strstr($filePath, "/..") || strstr($filePath, "../")) {
      throw new StaticSuiteException("File path can not contain '..': " . $filePath);
    }

    clearstatcache(TRUE, $filePath);
    if (is_dir($filePath) || is_link($filePath)) {
      throw new StaticSuiteException("Trying to delete a non-file path: " . $filePath);
    }

    // This exception should never be thrown.
    clearstatcache(TRUE, $filePath);
    if (!is_file($filePath)) {
      throw new StaticSuiteException("Trying to delete a non-existent file: " . $filePath);
    }

    clearstatcache(TRUE, $filePath);
    if (!is_writable($filePath)) {
      throw new StaticSuiteException("Not enough permissions to delete file: " . $filePath);
    }

    $result = $this->fileSystem->unlink($filePath);
    if ($result === FALSE) {
      throw new StaticSuiteException("Error while trying to delete a file: " . $filePath);
    }
    return TRUE;
  }

  /**
   * Tell whether a queue item is stale.
   *
   * An item is stale when:
   *
   * a) Is done:
   *    It's unique-id-date is older than last commit date:
   *    this happens when an old FileCollection hasn't been processed and,
   *    after that, another FileCollection has been committed.
   * b) Is pending:
   *    More than QUEUE_INSERTION_MAX_SECONDS have elapsed since queue item
   *    creation.
   *
   * @param array $item
   *   A queue item.
   *
   * @return bool
   *   True if it's stale.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function isQueueItemStale(array $item) {
    $lastCommittedUniqueIdDate = $this->getLastLockCommittedUniqueIdDate();
    $this->exporter->logMessage("[writer] Using Last Committed Unique Id date: " . $lastCommittedUniqueIdDate->format("Y-m-d_H-i-s.u"));

    if ($item['done']) {
      // We can not use "<=" as a comparison operator, because it could happen
      // that two export processes start at the same micro second. Each of them
      // gets the same micro date but different unique id (micro date + rand) so
      // using "<=" would lead to only committing one of them.
      $isStale = $item['unique-id-date'] < $lastCommittedUniqueIdDate;
    }
    else {
      // Use abs() to avoid dead locks if unique-id-date is in the future.
      $isStale = abs(time() - $item['unique-id-date']->format('U')) > self::QUEUE_INSERTION_MAX_SECONDS;
    }
    return $isStale;
  }

  /**
   * Get last lock committed unique id as a DateTime object.
   *
   * @return \DateTime
   *   A DateTime representing a unique id timestamp.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function getLastLockCommittedUniqueIdDate() {
    $filePath = $this->getWorkDir() . "/" . self::LAST_LOCK_COMMITTED_UNIQUE_ID_FILE;
    $lastLockCommittedUniqueId = @file_get_contents($filePath);
    if ($lastLockCommittedUniqueId === FALSE) {
      $lastLockCommittedUniqueId = $this->uniqueIdHelper->getDefaultUniqueId();
    }
    return $this->uniqueIdHelper->getDateFromUniqueId($lastLockCommittedUniqueId);
  }

  /**
   * Pull an item from the queue, sorted by its name (creation time).
   *
   * @return array|bool
   *   A queue item, or false if queue is empty
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function pullItemFromQueue() {
    $pattern = $this->getWorkDir() . '/' . self::QUEUE_DIR . '/*';
    foreach (glob($pattern) as $filePath) {
      $isDone = strpos($filePath, self::QUEUE_PENDING_FILE_EXTENSION) === FALSE;
      // Don't trust $filePath being already on filesystem.
      // It could happen that it's a pending item that has just finished, thus
      // having been renamed.
      $fileContents = @file_get_contents($filePath);
      if ($fileContents === FALSE) {
        if ($isDone) {
          // Only throw an error for completed queue items.
          // Don't throw an error for pending items, because it's
          // possible that it has just finished and been renamed.
          throw new StaticSuiteException("Queue item '$filePath' is not readable.");
        }
      }
      else {
        // Get item unique id.
        $uniqueId = $this->fileSystem->basename($filePath);
        $uniqueIdDate = $this->uniqueIdHelper->getDateFromUniqueId($uniqueId);
        $item = [
          'file-path' => $filePath,
          'unique-id' => $uniqueId,
          'unique-id-date' => $uniqueIdDate,
          'done' => $isDone,
        ];
        if ($item['done']) {
          $fileCollection = unserialize($fileContents, [
            'allowed_classes' => [
              FileCollection::class,
              FileItem::class,
            ],
          ]);
          if (!($fileCollection instanceof FileCollection)) {
            throw new StaticSuiteException("Queue item '$filePath' doesn't contain a valid FileCollection.");
          }
          $item['file-collection'] = $fileCollection;
        }
        $this->exporter->logMessage("[writer] Pulling item from queue: " . $uniqueId);
        return $item;
      }

    }
    return FALSE;
  }

  /**
   * Add a FileCollection to the queue.
   *
   * @param FileCollection $fileCollection
   *   The FileCollection to add to queue.
   *
   * @return bool
   *   True if locked.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function finishQueueInsertion(FileCollection $fileCollection) {
    $this->exporter->logMessage("[writer] Queue insertion finishing...");
    $serializedData = serialize($fileCollection);
    $pendingQueueFilePath = $this->getPendingQueueFilePath();
    $queueFilePath = $this->getQueueFilePath();
    $this->writeFile($pendingQueueFilePath, $serializedData, TRUE, FALSE);
    $result = rename($pendingQueueFilePath, $queueFilePath);
    if ($result === FALSE) {
      throw new StaticSuiteException("Can not write to file " . $queueFilePath);
    }
    $this->exporter->logMessage("[writer] Queue insertion finished.");
    return TRUE;
  }

  /**
   * Start a insertion of data into the queue.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function startQueueInsertion() {
    // Honor the value defined in self::QUEUE_INSERTION_MAX_SECONDS.
    // This way, Apache won't kill this process before time.
    if (!$this->staticSuiteUtils->isRunningOnCli()) {
      set_time_limit(self::QUEUE_INSERTION_MAX_SECONDS);
    }
    $pendingQueueFilePath = $this->getPendingQueueFilePath();
    $this->writeFile($pendingQueueFilePath, $this->uniqueId(), FALSE, TRUE);
    return TRUE;
  }

  /**
   * Deletes a pending insertion of data.
   *
   * This happens when the exporter starts but doesn't finish its process
   * for whatever reason.
   *
   * It doesn't throw any exception, because this method is usually called from
   * an exception catch block. Throwing an exception hides previous exceptions.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise.
   */
  public function deletePendingQueueInsertion() {
    $result = FALSE;
    try {
      $pendingQueueFilePath = $this->getPendingQueueFilePath();
      $result = $this->deleteFile($pendingQueueFilePath);
    }
    catch (Throwable $e) {
      // Do nothing.
    }
    return $result;
  }

  /**
   * Get path for a pending queue file.
   *
   * @return string
   */
  protected function getPendingQueueFilePath() {
    return $this->getQueueFilePath() . self::QUEUE_PENDING_FILE_EXTENSION;
  }

  /**
   * Get path for the queue file.
   *
   * @return string
   *   The lock path.
   */
  protected function getQueueFilePath() {
    return $this->getWorkDir() . '/' . self::QUEUE_DIR . '/' . $this->uniqueId();
  }

  /**
   * Saves a message to the commit log file.
   *
   * @param string $message
   *   Message to log.
   */
  public function logCommitMessage(string $message) {
    try {
      $logFile = $this->getWorkDir() . "/" . self::COMMIT_LOG_FILE;
      $timeStamp = $this->staticSuiteUtils->getFormattedMicroDate("Y-m-d H:i:s.u");
      $lockFlag = $this->exporter->isLock() ? "[LOCKED]" : "[NOLOCK]";
      $line = getmypid() . " [$timeStamp] $lockFlag $message\n";
      file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
    catch (Throwable $e) {
      // Do nothing to avoid errors when we are simply logging.
    }
  }

  /**
   * Saves the last lock committed unique id.
   *
   * @param string $uniqueId
   *   The unique id to save.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function saveLastLockCommittedUniqueId(string $uniqueId) {
    $filePath = $this->getWorkDir() . "/" . self::LAST_LOCK_COMMITTED_UNIQUE_ID_FILE;
    $result = file_put_contents($filePath, $uniqueId, LOCK_EX);
    if ($result === FALSE) {
      throw new StaticSuiteException("Error while writing to file: " . $filePath);
    }
  }

  /**
   * Saves the last lock executed unique id.
   *
   * @param FileCollection $fileCollection
   *   The FileCollection to get the unique id to save.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function saveLastLockExecutedUniqueId(FileCollection $fileCollection) {
    $filePath = $this->getWorkDir() . "/" . self::LAST_LOCK_EXECUTED_LIVE_UNIQUE_ID_FILE;
    if ($fileCollection->isPreview()) {
      $filePath = $this->getWorkDir() . "/" . self::LAST_LOCK_EXECUTED_PREVIEW_UNIQUE_ID_FILE;
    }
    $result = file_put_contents($filePath, $fileCollection->uniqueId(), LOCK_EX);
    if ($result === FALSE) {
      throw new StaticSuiteException("Error while writing to file: " . $filePath);
    }
  }

}
