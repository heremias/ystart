<?php

namespace Drupal\static_export\Extension;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;

/**
 * Output Formatter Uninstall Validator.
 *
 * Prevents output formatter module from being uninstalled
 * whilst its output format is being used.
 */
class OutputFormatterUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Output formatter manager.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected $outputFormatterPluginManager;

  /**
   * Internal cache for output formatter providers.
   *
   * @var array
   */
  protected array $outputFormatterProviderCache;

  /**
   * OutputFormatterUninstallValidator constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterPluginManager
   *   Output formatter manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler, OutputFormatterPluginManagerInterface $outputFormatterPluginManager) {
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
    $this->outputFormatterPluginManager = $outputFormatterPluginManager;
    $this->outputFormatterProviderCache = [];
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module): array {
    $reasons = [];

    // Locale exporter.
    $localeOutputFormatProvider = $this->getOutputFormatterProviderBy('exportable_locale');
    if ($localeOutputFormatProvider && $module === $localeOutputFormatProvider) {
      $reasons[] = $this->t('To uninstall this module, please change the output format or change the status of "Export operations enabled" at the <a href="@url">exportable locales settings page</a>.',
        [
          '@url' => Url::fromRoute('static_export.exportable_locale.settings')
            ->toString(),
        ]);
    }

    // Configuration exporter.
    $configOutputFormatProvider = $this->getOutputFormatterProviderBy('exportable_config');
    if ($configOutputFormatProvider && $module === $configOutputFormatProvider) {
      $reasons[] = $this->t('To uninstall this module, please change the output format or change the status of "Export operations enabled" at the <a href="@url">config export settings page</a>.',
        [
          '@url' => Url::fromRoute('static_export.exportable_config.settings')
            ->toString(),
        ]);
    }

    // Redirect exporter.
    if ($this->moduleHandler->moduleExists('static_export_exporter_redirect')) {
      $format = $this->configFactory->get('static_export_exporter_redirect.settings')
        ->get('format');
      if ($format) {
        $redirectOutputFormatProvider = $this->getProviderBy($format);
        if ($redirectOutputFormatProvider && $module === $redirectOutputFormatProvider) {
          $reasons[] = $this->t('To uninstall this module, please change the output format at the <a href="@url">redirect exporter settings page</a>.',
            [
              '@url' => Url::fromRoute('static_export_exporter_redirect.settings')
                ->toString(),
            ]);
        }
      }
    }

    return $reasons;
  }

  /**
   * Get the module provider by static export settings.
   *
   * @param string $settingName
   *   The name of the setting.
   *
   * @return string|null
   *   The module name.
   */
  protected function getOutputFormatterProviderBy(string $settingName): ?string {
    if (!isset($this->outputFormatterProviderCache[$settingName])) {
      $exporterSettings = $this->configFactory->get('static_export.settings')
        ->get($settingName);

      $isEnabled = $exporterSettings['enabled'] ?? FALSE;
      $format = $exporterSettings['format'] ?? FALSE;
      $provider = NULL;
      if ($isEnabled && $format) {
        $provider = $this->getProviderBy($format);
      }
      $this->outputFormatterProviderCache[$settingName] = $provider;
    }
    return $this->outputFormatterProviderCache[$settingName];
  }

  /**
   * Get module provider by plugin id.
   *
   * @param string $id
   *   The plugin id.
   *
   * @return string|null
   *   The module name.
   */
  protected function getProviderBy(string $id): ?string {
    $exception_on_invalid = FALSE;
    $outputFormatter = $this->outputFormatterPluginManager->getDefinition($id, $exception_on_invalid);
    return $outputFormatter['provider'] ?? NULL;
  }

}
