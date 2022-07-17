<?php

namespace Drupal\static_export\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_suite\Lock\LockHelperInterface;
use Drupal\static_suite\StaticSuiteUserException;
use Drush\Commands\DrushCommands;
use Throwable;

/**
 * A Drush command file to copy data dir.
 */
class CopyDataCommands extends DrushCommands {

  /**
   * Drupal's config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The lock helper from Static Suite.
   *
   * @var \Drupal\static_suite\Lock\LockHelperInterface
   */
  protected LockHelperInterface $lockHelper;

  /**
   * The lock system.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Drupal file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * StaticExportCopyDataCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal's config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Drupal file system service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\static_suite\Lock\LockHelperInterface $lockHelper
   *   The lock helper.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FileSystemInterface $fileSystem, StreamWrapperManagerInterface $streamWrapperManager, LockHelperInterface $lockHelper) {
    parent::__construct();
    $this->configFactory = $config_factory;
    $this->fileSystem = $fileSystem;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->lockHelper = $lockHelper;
    $this->lock = $this->lockHelper->getLock();
  }

  /**
   * Copy data folder on given path.
   *
   * @param string $target
   *   Destination folder.
   * @param array $execOptions
   *   An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   * @command static-export:copy-data-dir
   *
   * @option tar target tar file
   * @usage drush static-export:copy-data-dir /data
   *   Copy data folder to /data dir
   * @usage drush static-export:copy-data-dir /data --tar=/data.tar
   *   Copy data folder to /data and tar it into /data.tar.
   * @usage drush static-export:copy-data-dir /data --tar=/data.tar --gzip
   *   Copy data folder to /data, tar it into /data.tar, and gzip it.
   * @aliases secdd, static-export-copy-data-dir
   *
   * @static_export Annotation for drush hooks.
   */
  public function copyDataDir(
    string $target,
    array $execOptions = [
      'tar' => NULL,
      'gzip' => FALSE,
    ]
  ): void {

    // Check --gzip validity.
    if (!empty($execOptions['gzip']) && empty($execOptions['tar'])) {
      throw new StaticSuiteUserException("--gzip option requires --tar option");
    }

    // Check --tar validity.
    if (!empty($execOptions['tar']) && !is_string($execOptions['tar'])) {
      throw new StaticSuiteUserException("--tar option must be a path");
    }

    $timeStart = microtime(TRUE);
    $scheme = $this->configFactory->get('static_export.settings')
      ->get('uri.scheme');
    $streamWrapper = $this->streamWrapperManager->getViaScheme($scheme);
    if (!$streamWrapper) {
      throw new StaticSuiteUserException('No stream wrapper available for scheme "' . $scheme . '://"');
    }

    if ($streamWrapper::getType() !== StreamWrapperInterface::LOCAL_NORMAL) {
      throw new StaticSuiteUserException('The stream wrapper "' . $scheme . '://" is remote and not compatible with this command.');
    }

    $dataDir = $streamWrapper->realpath();
    $workDir = $this->configFactory->get("static_export.settings")
      ->get("work_dir");

    $this->output()->write("Acquiring lock on target dir: ");
    if (!$this->lockHelper->acquireOrWait($target)) {
      throw new StaticSuiteUserException('Could not acquire lock on "' . $target . '", timeout reached.');
    }
    $this->output()->writeln("DONE");

    $this->output()->write("Acquiring lock on data dir: ");
    if (!$this->lockHelper->acquireOrWait(ExporterPluginInterface::DATA_DIR_LOCK_NAME)) {
      throw new StaticSuiteUserException('Could not acquire lock on "data dir", timeout reached.');
    }
    $this->output()->writeln("DONE");

    try {
      if (!is_dir($target) && !$this->fileSystem->mkdir($target, 0777, TRUE)) {
        throw new StaticSuiteUserException($target . " cannot be created");
      }
      if (!is_writable($target)) {
        throw new StaticSuiteUserException($target . " is not writable");
      }
      if (is_file($target)) {
        throw new StaticSuiteUserException($target . " is a file");
      }
      $exclude = '';
      if (strpos($workDir, $dataDir) !== FALSE) {
        $exclude = " --exclude='" . str_replace($dataDir . '/', '', $workDir) . "'";
      }
      $command = "rsync -av$exclude " . $dataDir . "/ $target --delete";
      $this->output()->write("Copying " . $dataDir . "/ to " . $target . ": ");
      exec($command, $output, $returnValue);
      if ($returnValue !== 0) {
        throw new StaticSuiteUserException("Unable to copy " . $dataDir . " to $target:\n" . implode("\n", $output));
      }
      $this->output()->writeln("DONE");
      $this->lock->release(ExporterPluginInterface::DATA_DIR_LOCK_NAME);
      $this->output()->writeln("Lock released on data dir.");

      $tarFile = $execOptions['tar'];
      if (!empty($tarFile)) {
        $this->output()->write("Archiving in " . $tarFile . ": ");
        if (!is_dir($this->fileSystem->dirname($tarFile))) {
          $this->fileSystem->mkdir($this->fileSystem->dirname($tarFile), 0777, TRUE);
        }

        if (!is_writable($this->fileSystem->dirname($tarFile))) {
          throw new StaticSuiteUserException($this->fileSystem->dirname($tarFile) . " is not writable");
        }

        if (is_file($tarFile) && !is_writable($tarFile)) {
          throw new StaticSuiteUserException($tarFile . " is not writable");
        }

        $tarOptions = $execOptions['gzip'] ? "zcf" : "cf";
        $command = "cd $target && tar -$tarOptions " . $tarFile . " *";
        exec($command, $output, $returnValue);
        if ($returnValue !== 0) {
          throw new StaticSuiteUserException("Unable to archive " . $target . " in " . $tarFile . ":\n" . implode("\n", $output));
        }
        $this->output()->writeln("DONE");
      }
    }
    catch (StaticSuiteUserException $e) {
      $this->logger()->error($e->getMessage());
    }
    catch (Throwable $e) {
      $this->logger()->error($e);
    }
    finally {
      $this->lock->release(ExporterPluginInterface::DATA_DIR_LOCK_NAME);
      $this->output()->writeln("Lock released on data dir.");

      $this->lock->release($target);
      $this->output()->writeln("Lock released on target dir.");
    }

    $this->output()
      ->writeln("TOTAL TIME: " . (round(microtime(TRUE) - $timeStart, 3)) . " secs.");
  }

}
