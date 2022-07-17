<?php

namespace Drupal\cl_components\Component;

use Drupal\cl_components\Exception\InvalidComponentException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use JsonSchema\Validator;

/**
 * Component metadata.
 */
final class ComponentMetadata {

  public const COMPONENT_TYPE_ORGANISM = 'organism';

  public const COMPONENT_TYPE_MOLECULE = 'molecule';

  public const COMPONENT_TYPE_ATOM = 'atom';

  public const COMPONENT_STATUS_READY = 'READY';

  public const COMPONENT_STATUS_DEPRECATED = 'DEPRECATED';

  public const COMPONENT_STATUS_BETA = 'BETA';

  public const COMPONENT_STATUS_WIP = 'WIP';

  /**
   * the absolute path to the component directory.
   *
   * @var string
   */
  private string $path;

  /**
   * The absolute URI to the component directory.
   *
   * @var string
   */
  private string $uri;

  /**
   * The available variants.
   *
   * @var array
   */
  private array $variants;

  /**
   * The component documentation.
   *
   * @var string
   */
  private string $documentation = '';

  /**
   * The component type.
   *
   * @var string
   */
  private string $componentType;

  /**
   * The status of the component.
   *
   * @var string
   */
  private string $status;

  /**
   * The machine name for the component.
   *
   * @var string
   */
  private string $machineName;

  /**
   * The component's name.
   *
   * @var string
   */
  private string $name;

  /**
   * The PNG path for the component thumbnail.
   *
   * @var string
   */
  private string $thumbnailPath = '';

  /**
   * The component group.
   *
   * @var string
   */
  private string $group;

  /**
   * The library dependencies.
   *
   * @var string[]
   */
  private array $libraryDependencies;

  /**
   * Schemas for the component.
   *
   * @var array[]
   *   The schemas.
   */
  private array $schemas = ['props' => []];

  /**
   * The component description.
   *
   * @var string
   */
  private string $description;

  /**
   * ComponentMetadata constructor.
   *
   * @param string $path
   *   The path to the component folder.
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   */
  public function __construct(string $path) {
    $this->path = $path;
    $generator = \Drupal::service('file_url_generator');
    assert($generator instanceof FileUrlGeneratorInterface);
    $this->uri = $generator->generateAbsoluteString($this->path);
    // Try to load the file: 'metadata.json'.
    $metadata_path = sprintf('%s/metadata.json', $path);
    // Load the metadata.
    $metadata_info = Json::decode(file_get_contents($metadata_path));
    $this->validateMetadataFile(Validator::arrayToObjectRecursive($metadata_info));

    $this->variants = $metadata_info['variants'] ?? [];

    $documentation_path = sprintf('%s/README.md', $path);
    if (file_exists($documentation_path) && class_exists('\League\CommonMark\CommonMarkConverter')) {
      $documentation_md = file_get_contents($documentation_path);
      // phpcs:ignore Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
      $converter = new \League\CommonMark\CommonMarkConverter();
      $this->documentation = $converter->convertToHtml($documentation_md);
    }

    $path_parts = explode('/', $path);
    $folder_name = end($path_parts);
    $this->machineName = $metadata_info['machineName'] ?? $folder_name;
    $this->name = $metadata_info['name'] ?? ucwords($this->machineName);
    $this->description = $metadata_info['description'] ?? '- Not available -';
    $this->status = $metadata_info['status'] ?? static::COMPONENT_STATUS_WIP;
    $this->componentType = $metadata_info['componentType'] ?? static::COMPONENT_TYPE_ORGANISM;
    $this->libraryDependencies = $metadata_info['libraryDependencies'] ?? [];

    // Load the PNG.
    $thumbnail_path = sprintf('%s/thumbnail.png', $path);
    if (file_exists($thumbnail_path)) {
      $this->thumbnailPath = $thumbnail_path;
    }

    $this->group = $metadata_info['group'] ?? 'All Components';

    // Save the schemas.
    $this->parseSchemaInfo($metadata_info);
  }

  /**
   * Validates the metadata info.
   *
   * @param object $metadata_info
   *   The loaded metadata info.
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   */
  private function validateMetadataFile(object $metadata_info): void {
    $validator = new Validator();
    $validator->validate(
      $metadata_info,
      (object) ['$ref' => 'file://' . dirname(__DIR__) . '/metadata.schema.json']
    );
    if (!$validator->isValid()) {
      $message_parts = array_map(
        fn(array $error): string => sprintf("[%s] %s", $error['property'], $error['message']),
        $validator->getErrors()
      );
      $message = implode("/n", $message_parts);
      throw new InvalidComponentException($message);
    }
  }

  /**
   * Parse the schema information.
   *
   * @param array $metadata_info
   *   The metadata information as decoded from "metadata.json".
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   */
  private function parseSchemaInfo(array $metadata_info): void {
    $default_props_schema = [
      'type' => 'object',
      'additionalProperties' => FALSE,
      'required' => [],
      'properties' => [
        'children' => ['type' => 'string'],
      ],
    ];
    $this->schemas = $metadata_info['schemas'] ?? ['props' => $default_props_schema];
    if (($this->schemas['props']['type'] ?? 'object') !== 'object') {
      throw new InvalidComponentException('The schema for the props in the component metadata is invalid. The schema should be of type "object".');
    }
    if ($this->schemas['props']['additionalProperties'] ?? FALSE) {
      throw new InvalidComponentException('The schema for the props in the component metadata is invalid. Arbitrary additional properties are not allowed.');
    }
    $this->schemas['props']['additionalProperties'] = FALSE;
    $this->schemas['props']['properties']['children'] = ['type' => 'string'];
    // Save the props.
    $schema_props = $metadata_info['schemas']['props'] ?? $default_props_schema;
    $required_info = $schema_props['required'] ?? [];
    foreach ($schema_props['properties'] ?? [] as $name => $schema) {
      $is_required = in_array($name, $required_info);
      // All props should also support "object" this allows deferring rendering
      // in Twig to the render pipeline.
      $type = $schema['type'] ?? '';
      if (!is_array($type)) {
        $type = [$type];
      }
      $type = array_merge($type, ['object']);
      $type = array_unique($type);
      $schema['type'] = $type;
      $this->schemas['props']['properties'][$name]['type'] = $type;
    }
  }

  /**
   * Gets the documentation.
   *
   * @return string
   *   The HTML documentation.
   */
  public function getDocumentation(): string {
    return $this->documentation;
  }

  /**
   * Gets the thumbnail path.
   *
   * @return string
   *   The path.
   */
  public function getThumbnailPath(): string {
    return $this->thumbnailPath;
  }

  /**
   * Normalizes the value object.
   *
   * @return array
   *   The normalized value object.
   */
  public function normalize(): array {
    return [
      'path' => $this->getPath(),
      'uri' => $this->getUri(),
      'machineName' => $this->getMachineName(),
      'status' => $this->getStatus(),
      'componentType' => $this->getComponentType(),
      'name' => $this->getName(),
      'group' => $this->getGroup(),
      'variants' => $this->getVariants(),
      'libraryDependencies' => $this->getLibraryDependencies(),
    ];
  }

  /**
   * Gets the path.
   *
   * @return string
   *   The path.
   */
  public function getPath(): string {
    return $this->path;
  }

  /**
   * Gets the URI.
   *
   * @return string
   *   The URI.
   */
  public function getUri(): string {
    return $this->uri;
  }

  /**
   * Gets the machine name.
   *
   * @return string
   *   The machine name.
   */
  public function getMachineName(): string {
    return $this->machineName;
  }

  /**
   * Gets the status.
   *
   * @return string
   *   The status.
   */
  public function getStatus(): string {
    return $this->status;
  }

  /**
   * Gets the component type.
   *
   * @return string
   *   The component type.
   */
  public function getComponentType(): string {
    return $this->componentType;
  }

  /**
   * Gets the name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Gets the group.
   *
   * @return string
   *   The group.
   */
  public function getGroup(): string {
    return $this->group;
  }

  /**
   * Gets the list of variants.
   *
   * @return string[]
   *   The variants.
   */
  public function getVariants(): array {
    return $this->variants;
  }

  /**
   * Gets the library dependencies.
   *
   * @return string[]
   *   The dependencies.
   */
  public function getLibraryDependencies(): array {
    return $this->libraryDependencies;
  }

  /**
   * Gets the schemas.
   *
   * @return array[][]
   *   The schemas.
   */
  public function getSchemas(): array {
    return $this->schemas;
  }

  /**
   * Get the description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return $this->description;
  }

}
