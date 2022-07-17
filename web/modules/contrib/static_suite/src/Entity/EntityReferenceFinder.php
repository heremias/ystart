<?php

namespace Drupal\static_suite\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Entity reference finder class.
 */
class EntityReferenceFinder implements EntityReferenceFinderInterface {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * A map of "entity_reference" fields across bundles.
   *
   * @var array
   */
  protected $entityReferenceFields;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityFieldManager $entityFieldManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get a map of "entity_reference" fields across bundles.
   *
   * This is a separate method to allow easy overriding it.
   *
   * @return array
   *   An array keyed by entity type. Each value is an array which keys are
   *   field names and value is an array with two entries:
   *   - type: The field type.
   *   - bundles: An associative array of the bundles in which the field
   *     appears, where the keys and values are both the bundle's machine name.
   */
  protected function getEntityReferenceFields(): array {
    if (!$this->entityReferenceFields) {
      $this->entityReferenceFields = $this->entityFieldManager->getFieldMapByFieldType('entity_reference');
      // TODO - temporarily remove taxonomy_term due to performance issues.
      unset($this->entityReferenceFields['taxonomy_term']);
    }
    return $this->entityReferenceFields;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to search.
   * @param int $maxRecursionLevel
   *   Maximum level of recursion. Defaults to 1.
   * @param int $currentRecursionLevel
   *   Current level of recursion. Defaults to 1.
   * @param array $referencingEntities
   *   Array of entities referencing another entity. Passed on every recursive
   *   call to hold the set of results.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function findReferences(EntityInterface $entity, int $maxRecursionLevel = 1, int $currentRecursionLevel = 1, array $referencingEntities = []): array {
    // Find the entity id string inside "entity_reference" fields.
    $entitiesContainingAnotherEntityIdInsideItsFields = $this->findEntitiesContainingAnotherEntityIdInsideItsFields($entity->id());

    // Entity ids can be repeated across different entity types (a node with
    // ID 1 and a media entity with ID 1), so we need to ensure that found
    // entity ids belong to the entity that we are trying to find.
    $entitiesPointingToAnotherEntity = $this->getEntitiesPointingToAnotherEntity($entitiesContainingAnotherEntityIdInsideItsFields, $entity);

    if (count($entitiesPointingToAnotherEntity) > 0) {
      foreach ($entitiesPointingToAnotherEntity as $entityPointingToAnotherEntity) {
        $uuid = $entityPointingToAnotherEntity->uuid();
        // Avoid adding the same entity again (avoids circular dependencies)
        if (empty($referencingEntities[$uuid])) {
          $referencingEntities[$uuid] = $entityPointingToAnotherEntity;
          // Continue searching if $maxRecursionLevel not reached.
          if ($maxRecursionLevel === 0 || $currentRecursionLevel < $maxRecursionLevel) {
            $referencingEntities = $this->findReferences($entityPointingToAnotherEntity, $maxRecursionLevel, $currentRecursionLevel + 1, $referencingEntities);
          }
        }
      }
    }

    return $referencingEntities;
  }

  /**
   * Search an entity id string inside "entity_reference" fields.
   *
   * This method searches for ids, an not for the entity itself. Once an id is
   * found, we must load its entity and ensure is the same one we are looking
   * for. That is done in getReferencingEntities() method.
   *
   * @param string $entityId
   *   Id of the entity to be searched.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of entity_reference fields by entity type that contain the entity
   *   id string.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function findEntitiesContainingAnotherEntityIdInsideItsFields(string $entityId): array {
    $entitiesContainingEntityIdInsideItsFields = [];

    // Get a map of "entity_reference" fields across bundles.
    $entityReferenceFields = $this->getEntityReferenceFields();

    // Loop through entity types and search inside its fields.
    foreach ($entityReferenceFields as $entityType => $allFields) {
      // Filter out invalid fields that don't start with "field_".
      $validFields = array_filter($allFields, static function ($fieldKey) {
        return strpos($fieldKey, 'field_') === 0;
      }, ARRAY_FILTER_USE_KEY);

      // Check whether this entity type supports "published".
      $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityType);
      $hasStatus = $entityTypeDefinition ? $entityTypeDefinition->hasKey('published') : FALSE;

      foreach ($validFields as $fieldKey => $data) {
        // This query returns entity ids, and not the id of the field.
        $query = $this->entityTypeManager->getStorage($entityType)
          ->getQuery('AND')
          ->condition($fieldKey, $entityId);
        if ($hasStatus) {
          $query->condition('status', 1);
        }
        $result = $query->execute();
        if (count($result) > 0) {
          $entitiesContainingEntityIdInsideItsFields[$entityType][$fieldKey] = $result;
        }
      }
    }

    return $entitiesContainingEntityIdInsideItsFields;
  }

  /**
   * Load entities from a set of possible referencing entities.
   *
   * Entity ids can be repeated across different entity types (a node with
   * ID 1 and a media entity with ID 1), so we need to ensure that found
   * entity ids belong to the entity that we are trying to find.
   *
   * @param array $entitiesContainingEntityIdStringInsideItsFields
   *   Array of entities that contain the entity id inside its fields.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we are searching for.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of entities referencing our entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntitiesPointingToAnotherEntity(array $entitiesContainingEntityIdStringInsideItsFields, EntityInterface $entity): array {
    $referencingEntities = [];
    foreach ($entitiesContainingEntityIdStringInsideItsFields as $entityType => $fields) {
      foreach ($fields as $field => $foundEntityIds) {
        foreach ($foundEntityIds as $foundEntityId) {
          $entityThatContainsEntityId = $this->entityTypeManager->getStorage($entityType)
            ->load($foundEntityId);
          if (!$entityThatContainsEntityId || !($entityThatContainsEntityId instanceof FieldableEntityInterface)) {
            continue;
          }
          $fieldEntity = $entityThatContainsEntityId->get($field);
          if (!$fieldEntity && !($fieldEntity instanceof EntityInterface)) {
            continue;
          }
          $referencedEntities = $fieldEntity->referencedEntities();
          if (!is_array($referencedEntities)) {
            $referencedEntities = [$referencedEntities];
          }
          foreach ($referencedEntities as $referencedEntity) {
            // Check that the entity that is being referenced is really the
            // one we are searching for.
            if ($referencedEntity->uuid() === $entity->uuid()) {
              // If entity is a paragraph, and given that paragraphs are
              // pieces of content that belong to another entity (usually a
              // node), and that some paragraphs can be referenced inside
              // another paragraphs, let's search for the parent root entity
              // that holds a reference to the paragraph.
              if ($entityThatContainsEntityId instanceof ParagraphInterface) {
                $paragraphRootParentEntity = $this->getParagraphParentEntity($entityThatContainsEntityId);
                $entityThatContainsEntityId = $paragraphRootParentEntity ?: $entityThatContainsEntityId;
              }

              // Avoid returning the same entity we are searching for.
              if ($entityThatContainsEntityId->uuid() === $entity->uuid()) {
                continue;
              }

              // Avoid unpublished content.
              if ($entityThatContainsEntityId instanceof EntityPublishedInterface && !$entityThatContainsEntityId->isPublished()) {
                continue;
              }

              // Finally, add $entityThatContainsEntityId to the stack of referencing entities.
              $referencingEntities[] = $entityThatContainsEntityId;
            }
          }
        }
      }
    }
    return $referencingEntities;
  }

  /**
   * Get a paragraph's parent entity until top root is obtained.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   Parent Entity.
   */
  protected function getParagraphParentEntity(ParagraphInterface $paragraph): ?ContentEntityInterface {
    $paragraphParentEntity = $paragraph->getParentEntity();
    if ($paragraphParentEntity instanceof ParagraphInterface) {
      return $this->getParagraphParentEntity($paragraphParentEntity);
    }
    return $paragraphParentEntity;
  }

}
