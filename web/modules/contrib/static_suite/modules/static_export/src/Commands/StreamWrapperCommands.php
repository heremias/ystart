<?php

namespace Drupal\static_export\Commands;

use DOMDocument;
use Drupal\Core\File\FileSystemInterface;
use Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface;
use Drush\Commands\DrushCommands;
use Exception;

/**
 * A Drush command file to interact with stream wrappers used by Static Export.
 */
class StreamWrapperCommands extends DrushCommands {

  /**
   * The URI factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface
   */
  protected $uriFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * StaticExportCommands constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   *
   * @param \Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface $uriFactory
   *   The URI factory.
   */
  public function __construct(FileSystemInterface $fileSystem, UriFactoryInterface $uriFactory) {
    parent::__construct();
    $this->fileSystem = $fileSystem;
    $this->uriFactory = $uriFactory;
  }

  /**
   * Print file on the standard input.
   *
   * @param string $uriTarget
   *   The URI target (without scheme) to be printed
   *   (e.g.- en/config/system-site.json)
   * @param array $execOptions
   *   An associative array of options:
   *   --pretty
   *     Whether the output should be pretty-printed. Default: false
   *   --format
   *     Which format should be used when pretty-printing. If not specified, it
   *     guess it from the URI extension.
   *     Supported values: json or xml.
   *
   * @command static-export:uri-cat
   *
   * @usage drush static-export:uri-cat en/config/system-site.json --pretty
   *   Prints the contents of the file "en/config/system-site.json". That file
   *   is stored in a location defined by its scheme. That scheme is taken
   *   from Static Export's settings.
   * @aliases seuricat
   *
   * @static_export Annotation for drush hooks.
   */
  public function uriCat(
    string $uriTarget,
    array $execOptions = [
      'pretty' => FALSE,
      'format' => NULL,
    ]
  ): void {
    $uri = $this->uriFactory->create($uriTarget);
    if (is_readable($uri)) {
      $uriContents = file_get_contents($uri);
      $outPut = $uriContents;
      $format = NULL;
      if ($execOptions['pretty'] === TRUE) {
        $format = $execOptions['format'] ?? pathinfo($uriTarget, PATHINFO_EXTENSION);
      }

      try {
        switch ($format) {
          case 'json':
            $outPut = json_encode(
              json_decode($uriContents, TRUE, 512, JSON_THROW_ON_ERROR),
              JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );
            break;

          case 'xml':
            $xml = @simplexml_load_string($uriContents);
            if ($xml) {
              $dom = new DOMDocument('1.0');
              $dom->preserveWhiteSpace = FALSE;
              $dom->formatOutput = TRUE;
              $dom->loadXML($xml->asXML());
              $outPut = $dom->saveXML();
            }
            break;
        }
      }
      catch (Exception $e) {
        $this->logger()
          ->error("Data cannot be pretty-printed using the format of your choice. Please, review the chosen format option.");
        return;
      }
      $this->output()->writeln($outPut);
    }
    else {
      $this->logger()
        ->error("Failed to open uri: file not present or not readable.");
    }
  }

  /**
   * Print URI's local path on the standard input.
   *
   * It works only for local stream wrappers.
   *
   * @param string $uriTarget
   *   The URI target (without scheme) to be printed
   *   (e.g.- en/config/system-site.json)
   *
   * @command static-export:uri-to-local
   *
   * @usage drush static-export:uri-to-local en/config/system-site.json
   *   Prints the local path of the file "en/config/system-site.json". That file
   *   is stored in a location defined by its scheme. That scheme is taken
   *   from Static Export's settings.
   * @aliases seurilocal
   *
   * @static_export Annotation for drush hooks.
   */
  public function uriToLocalPath(string $uriTarget): void {
    $uri = $this->uriFactory->create($uriTarget);
    $localPath = $this->fileSystem->realpath($uri);
    $this->output()->writeln($localPath);
  }

  /**
   * Delete URI.
   *
   * @param string $uriTarget
   *   The URI target (without scheme) to be deleted
   *   (e.g.- en/config/system-site.json)
   *
   * @command static-export:delete-uri
   *
   * @usage drush static-export:delete-uri en/config/system-site.json
   *   Deletes the file "en/config/system-site.json". That file is stored in a
   *   location defined by its scheme. That scheme is taken from Static Export's
   *   settings.
   * @aliases seurirm
   *
   * @static_export Annotation for drush hooks.
   * @static_export_data_dir_write Annotation for commands that write data.
   */
  public function deleteUri(string $uriTarget): void {
    $uri = $this->uriFactory->create($uriTarget);
    if (is_writable($uri)) {
      $this->fileSystem->unlink($uri);
      $this->logger()->notice("Uri successfully deleted");
    }
    else {
      $this->logger()
        ->error("Failed to delete uri: file not present or not writable.");
    }
  }

}
