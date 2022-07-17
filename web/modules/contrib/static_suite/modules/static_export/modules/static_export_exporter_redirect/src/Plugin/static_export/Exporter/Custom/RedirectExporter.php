<?php

namespace Drupal\static_export_exporter_redirect\Plugin\static_export\Exporter\Custom;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface;
use Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginBase;
use Drupal\static_export_exporter_redirect\RedirectProviderInterface;
use Drupal\static_suite\StaticSuiteUserException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Redirect rules exporter.
 *
 * @StaticCustomExporter(
 *  id = "redirect",
 *  label = @Translation("Redirect rules exporter"),
 *  description = @Translation("Export redirect rules from Redirect module."),
 * )
 */
class RedirectExporter extends CustomExporterPluginBase {

  /**
   * The redirect provider from Static Export redirect exporter.
   *
   * @var \Drupal\static_export_exporter_redirect\RedirectProviderInterface
   */
  protected RedirectProviderInterface $redirectProvider;

  /**
   * {@inheritdoc}
   */
  public function getExporterItem() {
    return 'redirect_exporter';
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemId(): string {
    return 'redirect_exporter';
  }

  /**
   * {@inheritdoc}
   */
  public function getExporterItemLabel(): string {
    return "Redirect exporter";
  }

  /**
   * {@inheritdoc}
   */
  protected function setExtraDependencies(ContainerInterface $container): void {
    $this->redirectProvider = $container->get("static_export_exporter_redirect.provider");
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function getOutputDefinition(): ?ExporterOutputConfigInterface {
    $format = $this->configFactory->get('static_export_exporter_redirect.settings')
      ->get('format') ?? 'csv';

    $extension = 'csv';
    if ($format !== 'csv') {
      try {
        $outputFormatter = $this->outputFormatterManager->getInstance(['plugin_id' => $format]);
      }
      catch (PluginException) {
        throw new StaticSuiteUserException("Unknown Static Export output format: " . $format);
      }
      $extension = $outputFormatter ? $outputFormatter->getPluginDefinition()['extension'] : $format;
    }

    return $this->exporterOutputConfigFactory->create(
      'url',
      'redirects',
      $extension
    )->setBaseDir('system');
  }

  /**
   * {@inheritdoc}
   */
  protected function calculateDataFromResolver(): array {
    $redirects = $this->redirectProvider->getAllRules();

    $config = $this->configFactory->get('static_export_exporter_redirect.settings');
    $format = $config->get('format') ?? 'csv';
    if ($format === 'csv') {
      $csvPattern = $config->get('csv.pattern') ?? static_export_exporter_redirect_get_default_csv_pattern();
      $csvData = '';
      $tokens = static_export_exporter_redirect_get_csv_tokens();
      $search = ['\t'];
      foreach ($tokens as $token) {
        $search[] = '[' . $token . ']';
      }
      foreach ($redirects as $redirect) {
        $replace = ["\t"];
        foreach ($tokens as $token) {
          $replace[] = $redirect[$token];
        }
        $csvData .= str_replace($search, $replace, $csvPattern) . "\n";
      }
      return [
        ExporterPluginInterface::OVERRIDE_FORMAT => $csvData,
      ];
    }

    return $redirects;
  }

}
