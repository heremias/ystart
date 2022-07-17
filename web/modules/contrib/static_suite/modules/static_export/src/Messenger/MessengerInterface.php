<?php

namespace Drupal\static_export\Messenger;

use Drupal\Core\Messenger\MessengerInterface as BaseMessengerInterface;
use Drupal\static_export\File\FileCollectionGroup;
use Throwable;

/**
 * An interface that extends core's MessengerInterface.
 */
interface MessengerInterface extends BaseMessengerInterface {

  /**
   * Adds a new message to the queue.
   *
   * The messages will be displayed in the order they got added later.
   *
   * @param \Throwable $exception
   *   Exception that will be processed to get its data and backtrace, to be
   *   able to be shown as a message on screen.
   * @param bool $repeat
   *   (optional) If this is FALSE and the message is already set, then the
   *   message won't be repeated. Defaults to FALSE.
   *
   * @return MessengerInterface
   */
  public function addException(Throwable $exception, $repeat = FALSE): MessengerInterface;

  /**
   * Shows a message coming from an array of FileCollection.
   *
   * @param \Drupal\static_export\File\FileCollectionGroup $fileCollectionGroup
   *   A FileCollectionGroup.
   *
   * @return MessengerInterface
   */
  public function addFileCollectionGroup(FileCollectionGroup $fileCollectionGroup): MessengerInterface;

}
