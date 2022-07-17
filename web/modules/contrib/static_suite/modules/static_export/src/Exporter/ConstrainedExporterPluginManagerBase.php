<?php

namespace Drupal\static_export\Exporter;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\static_suite\Plugin\CacheablePluginManager;

/**
 * A base class for constrained exporter managers.
 */
abstract class ConstrainedExporterPluginManagerBase extends CacheablePluginManager implements ConstrainedExporterPluginManagerInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    if ($plugin_id !== $this->getDefaultExporterId()) {
      throw new PluginException('This exporter manager is constrained by some configuration. Please, use createDefaultInstance() instead.');
    }
    return parent::createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    if ($options['plugin_id'] !== $this->getDefaultExporterId()) {
      throw new PluginException('This exporter manager is constrained by some configuration. Please, use getDefaultInstance() instead.');
    }
    return parent::getInstance($options);
  }

  /**
   * Get the id of the default exporter, as defined in configuration.
   *
   * @return string
   *   Id of the default exporter.
   */
  protected function getDefaultExporterId(): string {
    return (string) $this->configFactory->get('static_export.settings')
      ->get($this->getDefaultInstanceConfigurationName());
  }

}
