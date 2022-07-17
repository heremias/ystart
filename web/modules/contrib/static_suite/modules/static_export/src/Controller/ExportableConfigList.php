<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Type\Config\Output\Uri\Resolver\ConfigExporterUriResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Exportable configurations.
 */
class ExportableConfigList extends ControllerBase {

  /**
   * The config exporter path resolver.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\Output\Uri\Resolver\ConfigExporterUriResolverInterface
   */
  protected $configExporterUriResolver;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory is already declared in
   *   \Drupal\Core\Controller\ControllerBase.
   * @param \Drupal\static_export\Exporter\Type\Config\Output\Uri\Resolver\ConfigExporterUriResolverInterface $configExporterUriResolver
   *   The config exporter path resolver.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ConfigExporterUriResolverInterface $configExporterUriResolver) {
    $this->configFactory = $configFactory;
    $this->configExporterUriResolver = $configExporterUriResolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('static_export.config_exporter_uri_resolver')
    );
  }

  /**
   * Shows a table of exportable configurations.
   */
  public function listExportableConfigs(): array {
    if (!$this->configFactory->get('static_export.settings')
      ->get('exportable_config.enabled')) {
      $configUrl = Url::fromRoute('static_export.exportable_config.settings', [], ['absolute' => FALSE])
        ->toString();
      $this->messenger()
        ->addWarning($this->t('Export operations for configuration objects are disabled. Please, enable them in the <a href="@url">settings</a> page.', ['@url' => $configUrl]));
    }

    $tableHeader = [
      'config_name' => $this->t('Configuration object name'),
      'uri' => $this->t('Export file (inside data directory)'),
      'modification_date' => $this->t('Last modification date'),
      'operations' => $this->t('Operations'),
    ];
    $tableRows = [];
    $configObjectsToExport = $this->configFactory->get('static_export.settings')
      ->get('exportable_config.objects_to_export');
    if (is_array($configObjectsToExport)) {
      foreach ($configObjectsToExport as $configName) {
        $exportUris = $this->configExporterUriResolver->setConfigName($configName)
          ->getUris();
        $lastConfigName = NULL;
        foreach ($exportUris as $exportUri) {
          $modificationDate = @filemtime($exportUri);
          $isSubRow = $lastConfigName === $configName;

          $links = [
            'view' => [
              'title' => $this->t('View'),
              'weight' => 10,
              'url' => Url::fromUri(file_create_url($exportUri)),
              'attributes' => [
                'target' => '_blank',
              ],
            ],
          ];
          if (!$isSubRow) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'weight' => 10,
              'url' => Url::fromRoute('static_export.exportable_config.delete_form', ['exportable_configuration_name' => $configName]),
            ];
          }

          $tableRows[] = [
            'config_name' => $isSubRow ? '' : $configName,
            'uri' => $exportUri->getTarget(),
            'modification_date' => $modificationDate ? date('Y-m-d H:i:s', $modificationDate) : $this->t('Not available'),
            'operations' => [
              'data' => [
                '#type' => 'operations',
                '#links' => $links,
              ],
            ],
          ];
          $lastConfigName = $configName;
        }
      }
    }

    if (count($tableRows) === 0) {
      $tableRows[] = [
        'config_name' => $this->t('No exportable configuration available'),
        'uri' => NULL,
        'operations' => [],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $tableHeader,
      '#rows' => $tableRows,
      '#prefix' => '<p>' . $this->t('Configuration objects use a different translation strategy from entities. Instead of using a full translation system, they use an override system, which applies those overrides on top of the main configuration object. This means that configuration objects are always available, regardless of the current language. Hence, we export configuration data varied by all available languages at once, to offer the same functionality Drupal provides.') . '</p>',
    ];

  }

}
