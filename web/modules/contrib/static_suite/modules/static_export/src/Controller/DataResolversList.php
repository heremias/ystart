<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface;
use Drupal\static_suite\Utility\SettingsUrlResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list of data resolvers.
 */
class DataResolversList extends ControllerBase {

  /**
   * The settings URL resolver.
   *
   * @var \Drupal\static_suite\Utility\SettingsUrlResolverInterface
   */
  protected SettingsUrlResolverInterface $settingsUrlResolver;

  /**
   * The data resolver manager.
   *
   * @var \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface
   */
  protected $dataResolverManager;

  /**
   * Constructor.
   *
   * @param \Drupal\static_suite\Utility\SettingsUrlResolverInterface $settingsUrlResolver
   *   The settings URL resolver.
   * @param \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface $dataResolverManager
   *   The resolver manager.
   */
  public function __construct(SettingsUrlResolverInterface $settingsUrlResolver, DataResolverPluginManagerInterface $dataResolverManager) {
    $this->settingsUrlResolver = $settingsUrlResolver;
    $this->dataResolverManager = $dataResolverManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('static_suite.settings_url_resolver'),
      $container->get('plugin.manager.static_data_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function listDataResolvers() {
    $tableHeader = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'configuration' => $this->t('Configuration'),
    ];
    $definitions = $this->dataResolverManager->getDefinitions();
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
          'Each entity can be exported using a different data resolver. All available data resolvers are listed below. Data resolvers are extensible plugins, so if no one matches your needs, you can define your own by creating a plugin with a "@annotation" annotation.', ['@annotation' => '@StaticDataResolver']
      ) . '</p>',
      '#suffix' => count($tableRows) === 0 ? '<p>' . $this->t('No data resolver found') . '</p>' : NULL,
    ];

  }

}
