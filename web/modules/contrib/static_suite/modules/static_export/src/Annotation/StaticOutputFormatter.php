<?php

namespace Drupal\static_export\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Static Output Formatter plugin annotation object.
 *
 * @Annotation
 */
class StaticOutputFormatter extends Plugin {

  /**
   * The plugin ID. A lowercase string.
   *
   * This is NOT the file extension, even though it's the same  in most cases.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The file extension. A lowercase string WITHOUT dots, e.g.- "json" and not
   * ".json".
   *
   * @var string
   */
  public $extension;

  /**
   * The file mime type (e.g.- application/json, text/xml, etc)
   *
   * We cannot use Drupal's ExtensionMimeTypeGuesser because it doesn't support
   * JSON nor YAML extensions (WTF!?)
   *
   * @var string
   */
  public $mimetype;

}
