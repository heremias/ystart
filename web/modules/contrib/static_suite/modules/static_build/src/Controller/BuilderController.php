<?php

namespace Drupal\static_build\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a controller to execute a new build on demand.
 */
class BuilderController extends ControllerBase {

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The static builder plugin manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * Locale exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface
   */
  protected $localeExporterManager;

  /**
   * BuilderController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language Manager.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   The static builder plugin manager.
   * @param \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface $localeExporterManager
   *   Locale exporter manager.
   */
  public function __construct(
    RequestStack $requestStack,
    LanguageManagerInterface $languageManager,
    StaticBuilderPluginManagerInterface $staticBuilderPluginManager,
    LocaleExporterPluginManagerInterface $localeExporterManager
  ) {
    $this->request = $requestStack->getCurrentRequest();
    $this->languageManager = $languageManager;
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->localeExporterManager = $localeExporterManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('language_manager'),
      $container->get('plugin.manager.static_builder'),
      $container->get('plugin.manager.static_locale_exporter'),
    );
  }

  /**
   * Run a new build (export and build).
   *
   * @param string $builderId
   *   Build plugin id.
   * @param string $runMode
   *   Build mode: live or preview.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to previous page.
   */
  public function runBuild(string $builderId, string $runMode): RedirectResponse {
    try {
      $this->exportLocale();

      $this->executeBuild($builderId, $runMode);

      $message = $this->t('A new "@runMode" build for "@builderId"  has started.', [
        '@builderId' => $builderId,
        '@runMode' => $runMode,
      ]);
    }
    catch (\Exception $e) {
      $message = $e->getMessage();
    }

    $this->messenger()->addMessage($message);

    // Wait 2 seconds for the process to start to be redirected.
    sleep(2);
    return new RedirectResponse($this->request->query->get('destination'));
  }

  /**
   * Execute a new build.
   *
   * @param string $builderId
   *   Build plugin id.
   * @param string $runMode
   *   Build mode: live or preview.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function executeBuild(string $builderId, string $runMode): void {
    $plugin = $this->staticBuilderPluginManager->getInstance([
      'plugin_id' => $builderId,
      'configuration' => [
        'run-mode' => $runMode,
        'lock-mode' => StaticBuilderPluginInterface::LOCK_MODE_LIVE,
      ],
    ]);
    $plugin->init();
  }

  /**
   * Exports locale data to be able to run a new build.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function exportLocale(): void {
    $localeExporter = $this->localeExporterManager->getDefaultInstance();

    $execOptions = [
      'standalone' => TRUE,
      'log-to-file' => TRUE,
      'lock' => TRUE,
      'build' => FALSE,
    ];

    $localeExporter->setMustRequestBuild($execOptions['build']);
    $localeExporter->setIsForceWrite(TRUE);
    $localeExporter->export(
      ['langcode' => $this->languageManager->getDefaultLanguage()->getId()],
      $execOptions['standalone'],
      $execOptions['log-to-file'],
      $execOptions['lock']
    );
  }

}
