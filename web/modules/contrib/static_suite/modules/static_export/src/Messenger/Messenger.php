<?php

namespace Drupal\static_export\Messenger;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupTrait;
use Drupal\Core\Messenger\Messenger as BaseMessenger;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Error;
use Drupal\static_export\File\FileCollection;
use Drupal\static_export\File\FileCollectionFormatter;
use Drupal\static_export\File\FileCollectionGroup;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Throwable;

/**
 * Messenger service for showing messages to Drupal users.
 */
class Messenger extends BaseMessenger implements MessengerInterface {

  use MarkupTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The File Collection Formatter.
   *
   * @var \Drupal\static_export\File\FileCollectionFormatter
   */
  protected $fileCollectionFormatter;

  /**
   * Messenger constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface $flash_bag
   *   The flash bag.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The kill switch.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\static_export\File\FileCollectionFormatter $fileCollectionFormatter
   *   The File Collection Formatter.
   */
  public function __construct(FlashBagInterface $flash_bag, KillSwitch $killSwitch, AccountProxyInterface $currentUser, FileCollectionFormatter $fileCollectionFormatter) {
    parent::__construct($flash_bag, $killSwitch);
    $this->currentUser = $currentUser;
    $this->fileCollectionFormatter = $fileCollectionFormatter;
  }

  /**
   * Shows a message if user is allowed to view it.
   *
   * @param string $message
   *   A message.
   */

  /**
   * {@inheritdoc}
   *
   * Add a control to shows a message if user is allowed to view it.
   */
  public function addMessage($message, $type = self::TYPE_STATUS, $repeat = FALSE) {
    if ($this->currentUser->hasPermission('view static export logs')) {
      return parent::addMessage($message, $type, $repeat);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addException(Throwable $exception, $repeat = FALSE): MessengerInterface {
    $error = Error::decodeException($exception);
    // Backtrace array is not a valid replacement value for t().
    $backtrace = $error['backtrace'];
    unset($error['backtrace']);
    array_shift($backtrace);
    // Generate a backtrace containing only scalar argument values.
    $error['@backtrace'] = Error::formatBacktrace($backtrace);
    $message = new FormattableMarkup('%type: @message in %function (line %line of %file). <pre class="backtrace">@backtrace</pre>', $error);
    $this->addError($message, $repeat);
    return $this;
  }

  /**
   * Shows a message coming from a FileCollection.
   *
   * @param \Drupal\static_export\File\FileCollection $fileCollection
   *   A FileCollection.
   */
  protected function addFileCollection(FileCollection $fileCollection): void {
    if ($this->currentUser->hasPermission('view static export logs')) {
      $this->fileCollectionFormatter->setFileCollection($fileCollection);
      $htmlLines = $this->fileCollectionFormatter->getHtmlLines($this->currentUser);
      foreach ($htmlLines as $line) {
        $renderedMessage = Markup::create($line);
        $this->addStatus($renderedMessage);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addFileCollectionGroup(FileCollectionGroup $fileCollectionGroup): MessengerInterface {
    if (!$fileCollectionGroup->isEmpty() && $this->currentUser->hasPermission('view static export logs')) {
      $this->addStatus("Static Export operations:");
      foreach ($fileCollectionGroup->getFileCollections() as $delta => $fileCollection) {
        // $this->addStatus("#" . ($delta + 1) . " File Collection " . $fileCollection->uniqueId());
        // $this->addStatus(str_repeat('=', 80), TRUE);
        $this->addFileCollection($fileCollection);
      }
    }
    return $this;
  }

}
