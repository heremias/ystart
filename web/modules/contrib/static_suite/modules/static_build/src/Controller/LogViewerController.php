<?php

namespace Drupal\static_build\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * LogViewer controller.
 *
 * Provides a controller for viewing static_build log files.
 */
class LogViewerController extends ControllerBase {

  /**
   * The static builder plugin manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * FileViewer controller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   The static builder manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StaticBuilderPluginManagerInterface $staticBuilderPluginManager
  ) {
    $this->configFactory = $config_factory;
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.static_builder')
    );
  }

  /**
   * Controller to view a build log.
   *
   * @param string $builderId
   *   Builder id.
   * @param string $runMode
   *   A run mode to get releases for. Usually live or preview.
   * @param string $uniqueId
   *   Release's unique id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function viewBuildLog(string $builderId, string $runMode, string $uniqueId) {
    // Load the plugin to get its release manager.
    try {
      $builder = $this->staticBuilderPluginManager->getInstance([
        'plugin_id' => $builderId,
        'configuration' => ['run-mode' => $runMode],
      ]);
      $releaseManager = $builder->getReleaseManager();
    }
    catch (Throwable $e) {
      echo $e->getMessage();
      throw new ServiceUnavailableHttpException();
    }

    $release = $releaseManager->create($uniqueId);
    $buildLogFilePath = $release->task($builder->getTaskId())->getLogFilePath();

    if (is_file($buildLogFilePath)) {
      // Prepare response object.
      $response = new Response();
      $response->setContent(file_get_contents($buildLogFilePath));
      $response->headers->set('Content-Type', 'text/plain');

      // Return response object.
      return $response;
    }

    throw new NotFoundHttpException();

  }

}
