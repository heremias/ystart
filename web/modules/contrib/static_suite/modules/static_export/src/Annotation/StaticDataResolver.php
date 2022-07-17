<?php

namespace Drupal\static_export\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Static resolver plugin annotation object.
 *
 * @Annotation
 */
class StaticDataResolver extends Plugin {

  /**
   * The plugin ID. A lowercase string.
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
   * Format that a resolver uses to return its data. A lowercase string.
   *
   * Most resolvers won't return any specific format, but an array that will
   * be later formatted by a StaticOutputFormatter. In such cases, this field
   * must be left undefined.
   *
   * But if a resolver can't return an array, it must define its format here.
   * This format will be directly used, without getting it from any
   * StaticOutputFormatter.
   *
   * Since this format is not related to any current StaticOutputFormatter
   * available, it can be anything (txt, pdf, my-own-format, etc). This value is
   * also used as the file extension (.txt, .pdf, .my-own-format, etc)
   *
   * @var string
   */
  public $format;

}
