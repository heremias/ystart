<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * FileViewer controller.
 *
 * Provides a controller for viewing static export files..
 */
class FileViewerController extends ControllerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The mime type guesser.
   *
   * @var \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * The URI factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected $uriFactory;

  /**
   * The output formatter manager.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected $outputFormatterManager;

  /**
   * The data include loader.
   *
   * @var \Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderInterface
   */
  protected $dataIncludeLoader;

  /**
   * FileViewer controller constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface $mimeTypeGuesser
   *   The mime type guesser.
   * @param \Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface $uriFactory
   *   The URI factory.
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   The output formatter manager.
   * @param \Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderInterface $dataIncludeLoader
   *   The data include loader.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    MimeTypeGuesserInterface $mimeTypeGuesser,
    UriFactoryInterface $uriFactory,
    OutputFormatterPluginManagerInterface $outputFormatterManager,
    DataIncludeLoaderInterface $dataIncludeLoader
  ) {
    $this->currentUser = $current_user;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->uriFactory = $uriFactory;
    $this->outputFormatterManager = $outputFormatterManager;
    $this->dataIncludeLoader = $dataIncludeLoader;
  }

  /**
   * {@inheritdoc}
   *
   * @noinspection ReturnTypeCanBeDeclaredInspection
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('file.mime_type.guesser.static_export'),
      $container->get('static_export.uri_factory'),
      $container->get('plugin.manager.static_output_formatter'),
      $container->get('static_export.data_include_loader'),
    );
  }

  /**
   * Controller to return a POST or a GET parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function viewPath(Request $request) {
    $uriTarget = $request->query->get('uri_target');
    $includes = $request->query->has('includes');
    if (empty($request->query->get('uri_target'))) {
      throw new NotFoundHttpException();
    }

    $uri = $this->uriFactory->create($uriTarget);
    if (!file_exists($uri) || !is_readable($uri) || !is_file($uri)) {
      throw new NotFoundHttpException();
    }

    $content = $includes ? $this->dataIncludeLoader->loadUri($uri) : file_get_contents($uri);

    // Prepare response object.
    $response = new Response();
    $response->setContent($content);
    $response->headers->set('Content-Type', $this->mimeTypeGuesser->guessMimeType($uri) ?: 'text/plain');

    // Return response object.
    return $response;
  }

}
