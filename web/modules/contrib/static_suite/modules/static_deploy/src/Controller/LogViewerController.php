<?php

namespace Drupal\static_deploy\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * LogViewer controller.
 *
 * Provides a controller for viewing static_deploy log files.
 */
class LogViewerController extends ControllerBase {

  /**
   * The static builder plugin manager.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;

  /**
   * FileViewer controller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface $staticDeployerPluginManager
   *   The static deployer manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $languageManager,
    StaticDeployerPluginManagerInterface $staticDeployerPluginManager
  ) {
    $this->configFactory = $config_factory;
    $this->languageManager = $languageManager;
    $this->staticDeployerPluginManager = $staticDeployerPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('plugin.manager.static_deployer')
    );
  }

  /**
   * Controller to view a build log.
   *
   * @param string $builderId
   *   Static Builder ID.
   * @param string $deployerId
   *   Static Deployer ID.
   * @param string $uniqueId
   *   Release's unique id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function viewDeployLog(string $builderId, string $deployerId, string $uniqueId) {
    // Load the static deployer plugin to get its release manager.
    $releaseManager = NULL;
    try {
      $deployer = $this->staticDeployerPluginManager->getInstance([
        'plugin_id' => $deployerId,
        'configuration' => ['builder-id' => $builderId],
      ]);
      $releaseManager = $deployer->getReleaseManager();
    }
    catch (Throwable $e) {
      throw new ServiceUnavailableHttpException();
    }

    $deployTaskId = StaticDeployerPluginInterface::TASK_ID . '-' . $deployer->getPluginId();
    $release = $releaseManager->create($uniqueId);
    $deployLogFilePath = $release->task($deployTaskId)->getLogFilePath();

    if (is_file($deployLogFilePath)) {
      // Prepare response object.
      $response = new Response();
      $response->setContent(file_get_contents($deployLogFilePath));
      $response->headers->set('Content-Type', 'text/plain');

      // Return response object.
      return $response;
    }

    throw new NotFoundHttpException();

  }

}
