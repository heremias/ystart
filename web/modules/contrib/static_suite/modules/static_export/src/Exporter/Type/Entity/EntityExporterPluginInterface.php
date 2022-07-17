<?php

namespace Drupal\static_export\Exporter\Type\Entity;

use Drupal\static_export\Exporter\ExporterPluginInterface;

/**
 * Defines an interface for entity exporters.
 */
interface EntityExporterPluginInterface extends ExporterPluginInterface {

  public const FILES_PER_DIR = 10000;

  public const OPTIONAL_SUB_DIR_TOKEN = DIRECTORY_SEPARATOR . '__OPTIONAL_SUB_DIR__';

  public const ENTITY_ID_TOKEN = '__ENTITY_ID__';

  // @todo - define methods so exporter options are typed.
}
