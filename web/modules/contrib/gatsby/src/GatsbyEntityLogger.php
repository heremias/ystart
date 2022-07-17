<?php

namespace Drupal\gatsby;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\gatsby\Entity\GatsbyLogEntityInterface;
use Drupal\jsonapi_extras\EntityToJsonApi;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Defines a service for logging content entity changes using log entities.
 */
class GatsbyEntityLogger {

  /**
   * Config Interface for accessing site configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\jsonapi_extras\EntityToJsonApi definition.
   *
   * @var \Drupal\jsonapi_extras\EntityToJsonApi
   */
  private $entityToJsonApi;

  /**
   * Drupal\Core\Entity\EntityRepository definition.
   *
   * @var \Drupal\Core\Entity\EntityRepository
   */
  private $entityRepository;

  /**
   * Constructs a new GatsbyEntityLogger object.
   */
  public function __construct(ConfigFactoryInterface $config,
      EntityTypeManagerInterface $entity_type_manager,
      EntityToJsonApi $entity_to_json_api,
      EntityRepository $entity_repository) {
    $this->config = $config->get('gatsby.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->entityToJsonApi = $entity_to_json_api;
    $this->entityRepository = $entity_repository;
  }

  /**
   * Logs an entity create, update, or delete.
   *
   * @param Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to log the details for.
   * @param string $action
   *   The action for this entity (insert, update, or delete).
   * @param string $type
   *   Indicate whether log is designated for preview or builds.
   */
  public function logEntity(ContentEntityInterface $entity, string $action, string $type = 'build') {
    $this->deleteLoggedEntity($entity->uuid(), $entity->language()->getId(), $type);

    $json = [];

    // @todo Document why this is necessary for this entity type but not others.
    if ($action !== 'delete') {
      if ($entity->getEntityTypeId() === 'paragraph') {
        return;
      }

      // Generate the full JSON representation of this entity so it can be
      // transmitted.
      $json = $this->getJson($entity, TRUE);

      // If nothing is returned then just fail.
      if (empty($json)) {
        // @todo Should this throw an exception?
        return;
      }
    }

    // Some additional meta data necessary for this entity.
    // @todo Clean this up.
    $json['id'] = $entity->uuid();
    $json['action'] = $action;
    $json['langcode'] = $entity->language()->getId();
    $json['type'] = $entity->getEntityTypeId() . '--' . $entity->bundle();

    // We keep langcode in both spots to help with backwards compatibility.
    // @todo Refactor gatsby-source-drupal to use the other data.
    $json['attributes'] = [
      'langcode' => $entity->language()->getId(),
      'drupal_internal__revision_id' => $entity->getRevisionId(),
    ];

    // Deletions require this nested data structure.
    // @todo Refactor gatsby-source-drupal to use the other data.
    if ($action === 'delete') {
      $json['data'] = $json;
    }

    // Build and save the log record.
    $log_entry = [
      'entity_uuid' => $entity->uuid(),
      'title' => $entity->label(),
      'entity' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'langcode' => $entity->language()->getId(),
      'action' => $action,
      'preview' => ($type == 'preview'),
      'published' => (method_exists($entity, 'isPublished') && $entity->isPublished()),
      'json' => json_encode($json),
    ];
    $log = $this->entityTypeManager->getStorage('gatsby_log_entity')
      ->create($log_entry);
    $log->save();
  }

  /**
   * Deletes existing entities based on uuid.
   *
   * @param string $uuid
   *   The entity uuid to delete the log entries for.
   * @param string $langcode
   *   The entity langcode.
   * @param string $type
   *   Indicate whether log is designated for preview or builds.
   */
  public function deleteLoggedEntity($uuid, $langcode = 'en', string $type = 'build') {
    $query = $this->entityTypeManager->getStorage('gatsby_log_entity')->getQuery()->accessCheck(FALSE);
    $entity_uuids = $query
      ->condition('entity_uuid', $uuid)
      ->condition('langcode', $langcode)
      ->condition('preview', $type == 'preview')
      ->execute();
    $entities = $this->entityTypeManager
      ->getStorage('gatsby_log_entity')
      ->loadMultiple($entity_uuids);

    foreach ($entities as $entity) {
      $entity->delete();
    }
  }

  /**
   * Deletes old or expired existing logged entities based on timestamp.
   *
   * @param int $timestamp
   *   The entity uuid to delete the log entries for.
   */
  public function deleteExpiredLoggedEntities($timestamp) {
    $query = $this->entityTypeManager->getStorage('gatsby_log_entity')->getQuery()->accessCheck(FALSE);
    $entity_uuids = $query->condition('created', $timestamp, '<')->execute();
    $entities = $this->entityTypeManager->getStorage('gatsby_log_entity')
      ->loadMultiple($entity_uuids);

    foreach ($entities as $entity) {
      $entity->delete();
    }
  }

  /**
   * Get the oldest created timestampe for a logged entity.
   */
  public function getOldestLoggedEntityTimestamp() {
    $query = $this->entityTypeManager->getStorage('gatsby_log_entity')->getQuery()->accessCheck(FALSE);
    $entity_uuids = $query->sort('created')->range(0, 1)->execute();
    $entities = $this->entityTypeManager->getStorage('gatsby_log_entity')
      ->loadMultiple($entity_uuids);

    if (!empty($entities)) {
      $entity = array_pop($entities);
      return $entity->getCreatedTime();
    }

    return FALSE;
  }

  /**
   * Gets log entities for a sync based on last fetched timestamp.
   *
   * @param int $last_fetch
   *   The time the sync was last fetched.
   *
   * @return array
   *   The JSON data for the entities to sync.
   */
  public function getSync($last_fetch) {
    // Get the current user.
    $user = \Drupal::currentUser();

    // Check for permissions to view preview entities.
    $show_preview = $user->hasPermission('sync gatsby fastbuild preview log entities');

    $query = $this->entityTypeManager->getStorage('gatsby_log_entity')->getQuery()->accessCheck(FALSE);

    // Add condition to filter logs by whether or not these are designated
    // "preview" entries.
    $query->condition('preview', $show_preview);
    
    $entity_uuids = $query->condition('created', $last_fetch, '>')
      ->sort('created')->execute();

    $entities = $this->entityTypeManager->getStorage('gatsby_log_entity')
      ->loadMultiple($entity_uuids);

    $sync_data = [
      'timestamp' => time(),
      'entities' => [],
    ];

    foreach ($entities as $entity) {
      if ($entity instanceof GatsbyLogEntityInterface) {
        $sync_data['timestamp'] = $entity->getCreatedTime();
        $sync_data['entities'][] = json_decode($entity->get('json')->value);
      }
    }

    return $sync_data;
  }

  /**
   * Gets the JSON object for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to JSONify.
   * @param bool $recursive
   *   Whether to get all of the referenced objects on this entity; defaults to
   *   FALSE.
   *
   * @todo This should be a global service.
   */
  public function getJson(ContentEntityInterface $entity, $recursive = FALSE) {
    try {
      $json = $this->entityToJsonApi->normalize($entity);
    }
    catch (RouteNotFoundException $e) {
      return NULL;
    }

    // Optionally handle nested entities.
    if ($recursive && !empty($json['data']['relationships'])) {
      // Generate JSON for all related entities to send to Gatsby.
      $entity_data = [];
      $included_types = $this->config->get('supported_entity_types') ?: [];
      $this->buildRelationshipJson($json['data']['relationships'], $entity_data, array_values($included_types));

      // Append the new data to the existing data structure, but remove the
      // UUIDs.
      if (!empty($entity_data)) {
        // Remove the uuid keys from the array.
        $entity_data = array_values($entity_data);

        $original_data = $json['data'];
        $entity_data[] = $original_data;
        $json['data'] = $entity_data;
      }
    }

    return $json;
  }

  /**
   * Builds an array of entity JSON data based on entity relationships.
   *
   * Works recursively.
   *
   * @param array $relationships
   *   The entities to load.
   * @param array $entity_data
   *   The arrays to add.
   * @param array $included_types
   *   The entity types to include.
   *
   * @todo Refactor this, it is really messy that the resultant data is added to
   *   the second argument.
   * @todo This should be a global service.
   */
  public function buildRelationshipJson(array $relationships, array &$entity_data, array $included_types = []) {
    foreach ($relationships as $data) {
      if (empty($data['data'])) {
        continue;
      }

      $related_items = $data['data'];

      // Check if this is a single value field.
      if (!empty($data['data']['type'])) {
        $related_items = [$data['data']];
      }

      foreach ($related_items as $related_data) {
        // Add JSON if the entity type is one that should be sent to Gatsby.
        $entityType = !empty($related_data['type']) ? explode('--', $related_data['type']) : "";
        if (!empty($entityType) && (!$included_types || in_array($entityType[0], $included_types, TRUE))) {
          // Skip this entity if it's already been added to the data array.
          if (!empty($entity_data[$related_data['id']])) {
            continue;
          }

          $related_entity = $this->entityRepository->loadEntityByUuid($entityType[0], $related_data['id']);

          // Make sure the related entity is a valid entity.
          if (empty($related_entity) || !($related_entity instanceof ContentEntityInterface)) {
            continue;
          }

          $related_json = $this->getJson($related_entity);
          if (!$related_json || empty($related_json['data'])) {
            continue;
          }

          $entity_data[$related_data['id']] = $related_json['data'];
          // We need to traverse all related entities to get all relevant JSON.
          if (!empty($related_json['data']['relationships'])) {
            $this->buildRelationshipJson($related_json['data']['relationships'], $entity_data, $included_types);
          }
        }
      }
    }
  }

}
