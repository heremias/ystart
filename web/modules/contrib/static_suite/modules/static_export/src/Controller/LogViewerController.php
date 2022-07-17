<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * LogViewer controller.
 *
 * Provides a controller for viewing static_export log files.
 */
class LogViewerController extends ControllerBase {

  /**
   * FileViewer controller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Controller to view a export log.
   *
   * @param string $uniqueId
   *   A unique id from a export operation.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function viewExportLog(string $uniqueId) {
    $logFilePath = $this->configFactory->get('static_export.settings')
      ->get('log_dir') . "/" . $uniqueId . '.log';
    if (is_file($logFilePath)) {
      // Prepare response object.
      $response = new Response();
      $response->setContent(file_get_contents($logFilePath));
      $response->headers->set('Content-Type', 'text/plain');

      // Return response object.
      return $response;
    }

    throw new NotFoundHttpException();
  }

}
