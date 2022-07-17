<?php

namespace Drupal\gatsby;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Defines a class for generating Gatsby based previews.
 */
class GatsbyPreview {

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Config Interface for accessing site configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Tracks data changes that should be sent to Gatsby.
   *
   * @var array
   */
  public static $updateData = [];

  /**
   * Constructs a new GatsbyPreview object.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('gatsby');
  }

  /**
   * Updates Gatsby Data array.
   */
  public function updateData($key, $url, $json = FALSE, $preview_path = "") {
    self::$updateData[$key][$url] = [
      'url' => $url,
      'json' => $json,
      'path' => $preview_path,
    ];
  }

  /**
   * Prepares Gatsby Data to send to the preview and build servers.
   *
   * By preparing the data in a separate step we prevent multiple requests from
   * being sent to the preview or incremental builds servers if mulutiple
   * Drupal entities are update/created/deleted in a single request.
   */
  public function gatsbyPrepareData(ContentEntityInterface $entity = NULL) {
    $settings = $this->configFactory->get('gatsby.settings');
    $preview_url = $settings->get('preview_callback_url');

    if ($preview_url) {
      $this->updateData('preview', $preview_url);
    }

    $incrementalbuild_url = $settings->get('incrementalbuild_url');
    if (!$incrementalbuild_url) {
      return;
    }

    $build_published = $settings->get('build_published');
    if (!$build_published || ($entity instanceof NodeInterface && $entity->isPublished())) {
      $this->updateData('incrementalbuild', $incrementalbuild_url, FALSE);
    }
  }

  /**
   * Prepares Gatsby Deletes to send to the preview and build servers.
   *
   * This is a separate method to allow overriding services to override the
   * delete method to add additional data.
   */
  public function gatsbyPrepareDelete(ContentEntityInterface $entity = NULL) {
    $json = [
      'id' => $entity->uuid(),
      'action' => 'delete',
    ];

    $settings = $this->configFactory->get('gatsby.settings');
    $preview_url = $settings->get('preview_callback_url');
    if ($preview_url) {
      $this->updateData('preview', $preview_url, $json);
    }

    $incrementalbuild_url = $settings->get('incrementalbuild_url');
    if ($incrementalbuild_url) {
      $this->updateData('incrementalbuild', $incrementalbuild_url, $json);
    }
  }

  /**
   * Verify the entity is supported for syncing to the Gatsby site.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   If the entity type should be sent to Gatsby Preview.
   *
   * @deprecated in 2.0.0, use isSupportedEntity() instead.
   */
  public function isPreviewEntity(ContentEntityInterface $entity) {
    return $this->isSupportedEntity($entity);
  }

  /**
   * Verify the entity is supported for syncing to the Gatsby site.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   If the entity type should be sent to Gatsby Preview.
   */
  public function isSupportedEntity(EntityInterface $entity) {
    // Only content entities are supported.
    if (!($entity instanceof ContentEntityInterface)) {
      return FALSE;
    }

    $entity_type = $entity->getEntityTypeId();

    // A list of entity types that are not supported.
    $not_supported = [
      'gatsby_log_entity',
    ];
    if (in_array($entity_type, $not_supported, TRUE)) {
      return FALSE;
    }

    // Get the list of supported entity types.
    $supported_types = $this->configFactory->get('gatsby.settings')->get('supported_entity_types');
    if (empty($supported_types)) {
      return FALSE;
    }

    // Check to see if the entity type is supported.
    return in_array($entity_type, array_values($supported_types), TRUE);
  }

  /**
   * Returns true if a preview server or build server URL is configured.
   *
   * @return bool
   *   If a build or preview URL is configured.
   */
  public function isConfigured() {
    $settings = $this->configFactory->get('gatsby.settings');
    $preview_url = $settings->get('preview_callback_url');
    $incrementalbuild_url = $settings->get('incrementalbuild_url');

    if ($preview_url || $incrementalbuild_url) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Determine is FastBuilds integration is enabled.
   *
   * @return bool
   *   Whether FastBuilds integration is enabled.
   */
  public function isFastBuildsEnabled() {
    // @todo Rewrite this logic when FastBuilds is merged into the main module.
    return \Drupal::moduleHandler()->moduleExists('gatsby_fastbuilds');
  }

  /**
   * Triggers the refreshing of Gatsby preview and incremental builds.
   */
  public function gatsbyUpdate() {
    foreach (self::$updateData as $endpoint_type => $urls) {
      foreach ($urls as $data) {
        // Requests to the preview endpoint do not need the data packet if
        // FastBuilds is enabled.
        if ($endpoint_type == 'preview' && $this->isFastBuildsEnabled()) {
          $this->triggerRefresh($data['url'], $data['path']);
        }
        else {
          $this->triggerRefresh($data['url'], $data['path'], $data['json']);
        }
      }
    }

    // Reset update data to ensure it's only processed once.
    self::$updateData = [];
  }

  /**
   * Triggers Gatsby refresh endpoint.
   *
   * @param string $preview_callback_url
   *   The Gatsby URL to refresh.
   * @param string $path
   *   The path used to trigger the refresh endpoint.
   * @param object|bool $json
   *   Optional JSON object to post to the server.
   */
  public function triggerRefresh($preview_callback_url, $path = '', $json = FALSE) {
    // If the URL has a comma it means multiple end points need to be called.
    if (stripos($preview_callback_url, ',')) {
      $urls = array_map('trim', explode(',', $preview_callback_url));

      foreach ($urls as $url) {
        $this->triggerRefresh($url, $path, $json);
      }

      return;
    }

    // All of the values transmitted to the endpoint.
    $arguments = [
      // The default timeout is 30 seconds, which is a really long time for an
      // API, so time out really quickly.
      'timeout' => 1,
    ];

    // Add the JSON packet to the request if it was provided.
    if (!empty($json)) {
      $arguments['json'] = $json;
    }

    // Optionally log the data.
    if ($this->configFactory->get('gatsby.settings')->get('log_json')) {
      if (!empty($json)) {
        $this->logger->debug("Endpoint: {$preview_callback_url}\n<br />\n<br />" . json_encode($json));
      }
      else {
        $this->logger->debug($preview_callback_url);
      }
    }

    // Trigger the HTTP request.
    try {
      $this->httpClient->post($preview_callback_url . $path, $arguments);
    }
    catch (ConnectException $e) {
      // This is maintained for the legacy callback URL only.
      // Do nothing as no response is returned from the preview server.
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

}
