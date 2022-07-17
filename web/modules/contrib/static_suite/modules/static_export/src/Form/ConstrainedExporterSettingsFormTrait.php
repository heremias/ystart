<?php

namespace Drupal\static_export\Form;

use Drupal\static_suite\Utility\SettingsUrlResolverInterface;

/**
 * Base settings form for constrained exporters (entity, config, etc)
 */
trait ConstrainedExporterSettingsFormTrait {

  /**
   * The settings URL resolver.
   *
   * @var \Drupal\static_suite\Utility\SettingsUrlResolverInterface
   */
  protected SettingsUrlResolverInterface $settingsUrlResolver;

  /**
   * Get options for a tableselect form element.
   *
   * Get an array of options from a set of exporter definitions.
   *
   * @param array $definitions
   *   Set of exporter definitions.
   *
   * @return array
   *   Array of options for a tableselect form element.
   */
  public function getExporterDefinitionOptions(array $definitions): array {
    $options = NULL;
    foreach ($definitions as $pluginId => $pluginDefinition) {
      $option = [
        'id' => $pluginId,
        'name' => $pluginDefinition['label'],
        'description' => $pluginDefinition['description'],
      ];
      $configUrl = $this->settingsUrlResolver->setModule($pluginDefinition['provider'])
        ->resolve();
      if ($configUrl) {
        $option['configuration']['data'] = [
          '#title' => $this->t('Edit configuration'),
          '#type' => 'link',
          '#url' => $configUrl,
        ];
      }
      else {
        $option['configuration'] = $this->t('No configuration available');
      }
      $options[$pluginId] = $option;
    }

    return $options;
  }

}
