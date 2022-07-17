<?php

namespace Drupal\static_export\File\MimeType;

use Drupal\Core\File\MimeType\MimeTypeGuesser as BaseMimeTypeGuesser;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Defines a MIME type guesser for Static Export.
 */
class MimeTypeGuesser extends BaseMimeTypeGuesser {

  /**
   * The default mimeType guesser from Drupal's core.
   *
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected $defaultMimeTypeGuesser;

  /**
   * Constructs a MimeTypeGuesser object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Symfony\Component\Mime\MimeTypeGuesserInterface $defaultMimeTypeGuesser
   *   The default mimeType guesser from Drupal's core.
   */
  public function __construct(StreamWrapperManagerInterface $stream_wrapper_manager, MimeTypeGuesserInterface $defaultMimeTypeGuesser) {
    $this->defaultMimeTypeGuesser = $defaultMimeTypeGuesser;
    parent::__construct($stream_wrapper_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function guessMimeType($path): ?string {
    $mimetype = NULL;
    // \Drupal\Core\File\MimeType\MimeTypeGuesser::guessMimeType() is typed to
    // return "?string" (string or null) but it returns void when no Mime Type
    // can be guessed. Using a simple try/catch to get rid of that bug.
    // @todo remove this try/catch once
    //   https://www.drupal.org/project/drupal/issues/3219654 or
    //   https://www.drupal.org/project/drupal/issues/3156672 are in place
    try {
      $mimetype = parent::guessMimeType($path);
    }
    catch (\TypeError $e) {
      @trigger_error('Error while guessing Mime Type for path: ' . $path . '. ' . $e, E_USER_WARNING);
    }
    if (!$mimetype) {
      $mimetype = $this->defaultMimeTypeGuesser->guessMimeType($path);
    }
    return $mimetype;
  }

}
