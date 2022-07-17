<?php

namespace Drupal\static_export_stream_wrapper_git\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\static_export\Event\StaticExportEvent;
use Drupal\static_export\Event\StaticExportEvents;
use Drupal\static_export\File\FileCollectionFormatter;
use Drupal\static_export\File\FileCollectionGroup;
use Drupal\static_suite\Cli\CliCommandFactoryInterface;
use Drupal\static_suite\StaticSuiteException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for Git streamer wrapper.
 *
 * Git is a special stream wrapper since each write/delete operation should
 * synchronously commit changes and push them to the remote repository. That
 * approach, while being valid, is not desirable to the way Static Export works,
 * where a FileCollection is created with several files, and all of them
 * are saved at once. Hence, that FileCollection should be committed and pushed
 * for all files at once.
 *
 * There no way to achieve the above with standard Stream Wrappers, so we listen
 * to StaticExportEvents to execute that commit and push.
 */
class GitStreamEventSubscriber implements EventSubscriberInterface {

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The CLI command factory.
   *
   * @var \Drupal\static_suite\Cli\CliCommandFactoryInterface
   */
  protected $cliCommandFactory;

  /**
   * The File Collection formatter.
   *
   * @var \Drupal\static_export\File\FileCollectionFormatter
   */
  protected $fileCollectionFormatter;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Drupal file system service.
   * @param \Drupal\static_suite\Cli\CliCommandFactoryInterface $cliCommandFactory
   *   The CLI command factory.
   * @param \Drupal\static_export\File\FileCollectionFormatter $fileCollectionFormatter
   *   The File Collection formatter.
   */
  public function __construct(ConfigFactoryInterface $configFactory, FileSystemInterface $fileSystem, CliCommandFactoryInterface $cliCommandFactory, FileCollectionFormatter $fileCollectionFormatter) {
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->cliCommandFactory = $cliCommandFactory;
    $this->fileCollectionFormatter = $fileCollectionFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[StaticExportEvents::WRITE_QUEUE_PROCESSING_END][] = ['synchronizeRepository'];
    return $events;
  }

  /**
   * Synchronizes repository, executing commit and push.
   *
   * @param \Drupal\static_export\Event\StaticExportEvent $event
   *   The Static Export event.
   *
   * @return \Drupal\static_export\Event\StaticExportEvent
   *   The processed event.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function synchronizeRepository(StaticExportEvent $event): StaticExportEvent {
    // Check if static-git is the current stream wrapper for Static Export.
    if ($this->configFactory->get('static_export.settings')
      ->get('uri.scheme') !== 'static-git') {
      return $event;
    }

    $fileCollectionGroup = $event->getFileCollectionGroup();
    if ($fileCollectionGroup && $fileCollectionGroup->size() > 0) {
      $git = $this->configFactory->get('static_export_stream_wrapper_git.settings')
        ->get('git_binary');
      $repoDir = $this->configFactory->get('static_export_stream_wrapper_git.settings')
        ->get('repo_dir');

      // Get commit message from the file collection group.
      $commitMessage = $this->getCommitMessage($fileCollectionGroup);

      // Save commit message to a temporary file, so "git commit" can use it.
      $commitMessagePath = $this->saveCommitMessage($commitMessage);

      $commands = [
        $git . ' add .',
        $git . ' commit -F ' . $commitMessagePath . ' --quiet',
        $git . ' push --force --quiet',
      ];
      $cliCommandResult = $this->cliCommandFactory->create(implode(" && ", $commands), $repoDir)
        ->execute();
      // "0" means "no errors", and "1" "no changes in repo"
      if (!in_array($cliCommandResult->getReturnCode(), [0, 1])) {
        $this->fileSystem->unlink($commitMessagePath);
        throw new StaticSuiteException("Unable to synchronize repository on  " . $repoDir . ":\n" . $cliCommandResult->getStdOut() . $cliCommandResult->getStdErr());
      }
      $this->fileSystem->unlink($commitMessagePath);

    }
    return $event;
  }

  /**
   * Get the commit message for a file collection group.
   *
   * @param \Drupal\static_export\File\FileCollectionGroup $fileCollectionGroup
   *   A file collection group.
   *
   * @return string
   *   The commit message.
   */
  protected function getCommitMessage(FileCollectionGroup $fileCollectionGroup): string {
    $commitSubject = [];
    $commitBody = [];
    foreach ($fileCollectionGroup->getFileCollections() as $fileCollection) {
      $this->fileCollectionFormatter->setFileCollection($fileCollection);
      $textLines = $this->fileCollectionFormatter->getTextLines(0, 0, TRUE);
      $commitSubject[] = $textLines[0];
      $commitBody[] = implode("\n", $textLines);
    }
    return implode(" / ", $commitSubject) . "\n\n" . implode("\n\n", $commitBody);
  }

  /**
   * Save a commit message to a temporary file,, so "git commit" can use it.
   *
   * @param string $commitMessage
   *   The commit message to save.
   *
   * @return string
   *   The temporary file path.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function saveCommitMessage(string $commitMessage): string {
    $commitMessagePath = $this->fileSystem->getTempDirectory() . '/' . 'static-git-commit-message.' . md5($commitMessage) . '.txt';
    $result = file_put_contents($commitMessagePath, $commitMessage);
    if ($result === FALSE) {
      throw new StaticSuiteException('Unable to save commit message to file ' . $commitMessagePath);
    }
    return $commitMessagePath;
  }

}
