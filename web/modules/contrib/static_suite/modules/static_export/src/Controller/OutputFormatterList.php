<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Drupal\static_suite\Utility\SettingsUrlResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of output formatters.
 */
class OutputFormatterList extends ControllerBase {

  /**
   * The settings URL resolver.
   *
   * @var \Drupal\static_suite\Utility\SettingsUrlResolverInterface
   */
  protected SettingsUrlResolverInterface $settingsUrlResolver;

  /**
   * The output formatter manager.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected $outputFormatterManager;

  /**
   * Constructor.
   *
   * @param \Drupal\static_suite\Utility\SettingsUrlResolverInterface $settingsUrlResolver
   *   The settings URL resolver.
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   The output formatter manager.
   */
  public function __construct(SettingsUrlResolverInterface $settingsUrlResolver, OutputFormatterPluginManagerInterface $outputFormatterManager) {
    $this->settingsUrlResolver = $settingsUrlResolver;
    $this->outputFormatterManager = $outputFormatterManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('static_suite.settings_url_resolver'),
      $container->get('plugin.manager.static_output_formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function listFormatters() {
    $tableHeader = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'configuration' => $this->t('Configuration'),
    ];
    $definitions = $this->outputFormatterManager->getDefinitions();
    $tableRows = [];
    foreach ($definitions as $pluginId => $pluginDefinition) {
      $row = [
        'id' => $pluginId,
        'name' => $pluginDefinition['label'],
        'description' => $pluginDefinition['description'],
      ];
      $configUrl = $this->settingsUrlResolver->setModule($pluginDefinition['provider'])
        ->resolve();
      if ($configUrl) {
        $row['configuration']['data'] = [
          '#title' => $this->t('Edit configuration'),
          '#type' => 'link',
          '#url' => $configUrl,
        ];
      }
      else {
        $row['configuration'] = $this->t('No configuration available');
      }
      $tableRows[$pluginId] = $row;
    }

    return [
      '#type' => 'table',
      '#header' => $tableHeader,
      '#rows' => $tableRows,
      '#prefix' => '<p>' . $this->t(
          'Exportable entities, configurations and locales can be exported using several output formatters. All available formatters are listed below. Formatters are extensible plugins, so if no one matches your needs, you can define your own by creating a plugin with a "@annotation" annotation.', ['@annotation' => '@StaticOutputFormatter']
      ) . '</p>',
      '#suffix' => count($tableRows) === 0 ? '<p>' . $this->t('No output formatter found') . '</p>' : NULL,
    ];

  }

}
