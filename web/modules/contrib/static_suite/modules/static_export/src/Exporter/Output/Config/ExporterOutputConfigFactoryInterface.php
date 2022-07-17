<?php

namespace Drupal\static_export\Exporter\Output\Config;

use Drupal\Core\Language\LanguageInterface;

/**
 * An interface for factories that create ExporterOutputConfigInterface objects.
 */
interface ExporterOutputConfigFactoryInterface {

  /**
   * Get default export base directory.
   *
   * @return string
   *   The default export base directory. This is a fixed value that is defined
   *   in this module's services.yml.
   */
  public function getDefaultBaseDir(): string;

  /**
   * Creates a ExporterOutputConfigInterface object.
   *
   * Instead of having a single parameter called $path, this method is split
   * out into its different parts ($dir, $filename, $extension, $language and
   * $format). This approach has some benefits:
   *  - avoids having to deal with string concatenations
   *  - avoids having to parse strings to get the path's extension (required to
   *    get its format if format is not defined)
   *  - makes it simpler to override this configuration when an event is
   *    dispatched by an exporter
   *  - simplifies the addition of dynamically calculated subdirectories to the
   *    path (e.g.- EntityExporterInterface::OPTIONAL_SUB_DIR_TOKEN).
   *
   * The only downside of this approach is that, since we want to maintain the
   * natural order of elements ($dir/$filename.$extension) we don't
   * allow a NULL value but an empty string for $dir. The reason is that
   * defining an optional parameter before a required one ($filename) is bad
   * practice.
   *
   * Here there are some example of how should this method be used:
   *
   * @code
   *  $factory->create('my-dir/my-sub-dir', 'my-file', 'json', $language);
   *  $factory->create('my-dir', 'my-file', 'json', $language);
   *  $factory->create('', 'my-file', 'json', $language);
   *  $factory->create('', 'my-file', $language);
   * @endcode
   *
   * If output format needs to be set (it's usually derived from extension), it
   * can be done as follows:
   * @code
   *  $factory->create('my-dir', 'my-file', 'my-extension', $language, 'json');
   * @endcode
   *
   * There is another important path called base dir, which is the root
   * directory where the above path is saved. That base dir is a fixed string
   * for each kind of exporter (entity, config, etc.). It can be changed on an
   * exporter basis, but in order to make that change a conscious one, base dir
   * can not be defined in this method, and should be done as follows:
   * @code
   *  $outputConfig = $factory->create('my-dir', 'my-file', 'json', $language);
   *  $outputConfig->setBaseDir('my-base-dir')
   * @endcode
   *
   * @param string $dir
   *   The export directory, relative to base dir inside data dir. It can
   *   contain subdirectories.
   *   Optional, it can be an empty string. It cannot be NULL, because we want
   *   to maintain the natural order of elements in a path
   *   ($dir/$filename.$extension) and defining an optional parameter before a
   *   required one ($filename) is bad practice.
   *   Hence, if an empty string is passed, we consider it to be NULL.
   * @param string $filename
   *   Export filename.
   *   Required. It must be a string with letters or numbers, with a minimum
   *   length of one character. Not meeting this requirement throws an error.
   * @param string|null $extension
   *   Export extension.
   *   Optional, it can be null or an empty string.
   * @param \Drupal\Core\Language\LanguageInterface|null $language
   *   Optional export language.
   *   If not defined, it uses a language suited for non-linguistic content
   *   (LanguageInterface::LANGCODE_NOT_APPLICABLE), which makes the language
   *   not appearing in the resulting uri.
   *   Even though language, if present, is the first part of the resulting
   *   path, here we define it later in this method's signature because it's
   *   optional, and we should understand $language as a way of altering paths.
   * @param string|null $format
   *   Optional export format.
   *   If not defined, it uses the extension from the path. If extension is not
   *   defined, it will return null. This is a valid behavior, because format
   *   is only used when data formatting happens, and that is an optional step
   *   (exporters can opt out of formatting using
   *   ExporterPluginInterface::OVERRIDE_FORMAT). This argument is rarely used,
   *   so it's the last one of this method's signature, instead of being next
   *   to $extension.
   *
   * @return \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface
   *   An exporter output config.
   */
  public function create(string $dir, string $filename, string $extension = NULL, LanguageInterface $language = NULL, string $format = NULL): ExporterOutputConfigInterface;

}
