<?php

namespace Drupal\static_export\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Static Data Include Loader plugin annotation object.
 *
 * @Annotation
 */
class StaticDataIncludeLoader extends Plugin {

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
   * The supported mimetype, in lowercase.
   *
   * This value comes from the "mimetype" field in plugins defined by
   * StaticOutputFormatter annotation. Static Suite supports, by default,
   * the following mimetypes: application/json, text/xml y text/yaml.
   *
   * @var string
   */
  public $mimetype;

}
