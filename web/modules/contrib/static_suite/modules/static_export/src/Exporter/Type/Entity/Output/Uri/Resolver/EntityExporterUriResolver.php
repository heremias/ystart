<?php

namespace Drupal\static_export\Exporter\Type\Entity\Output\Uri\Resolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\static_export\Exporter\Output\Uri\Resolver\ExporterUriResolverBase;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface;
use Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface;
use Drupal\static_suite\Entity\EntityUtilsInterface;

/**
 * URI resolver service for exported entities.
 */
class EntityExporterUriResolver extends ExporterUriResolverBase implements EntityExporterUriResolverInterface {

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Entity exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface
   */
  protected $entityExporterPluginManager;

  /**
   * Entity exporter instance.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginInterface
   */
  protected $entityExporter;

  /**
   * Custom exporter Manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface
   */
  protected $customExporterManager;

  /**
   * The entity utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtilsInterface
   */
  protected $entityUtils;

  /**
   * Entity to work with.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * An internal cache for custom exported URIs.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\UriInterface[]
   */
  protected $customExportedUriCache;

  /**
   * URI Resolver service constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language Manager.
   * @param \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface $entityExporterPluginManager
   *   Entity exporter Manager.
   * @param \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface $customExporterManager
   *   Custom exporter Manager.
   * @param \Drupal\static_suite\Entity\EntityUtilsInterface $entityUtils
   *   Entity utils.
   */
  public function __construct(
    LanguageManagerInterface $languageManager,
    EntityExporterPluginManagerInterface $entityExporterPluginManager,
    CustomExporterPluginManagerInterface $customExporterManager,
    EntityUtilsInterface $entityUtils
  ) {
    $this->languageManager = $languageManager;
    $this->entityExporterPluginManager = $entityExporterPluginManager;
    $this->customExporterManager = $customExporterManager;
    $this->entityUtils = $entityUtils;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function setEntity(EntityInterface $entity): EntityExporterUriResolverInterface {
    $this->entity = $entity;
    $this->entityExporter = $this->entityExporterPluginManager->createDefaultInstance();
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function getMainUri(): ?UriInterface {
    return $this->entityExporter
      ->setOptions(['entity' => $this->entity])
      ->getUri();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getVariantUris(LanguageInterface $language = NULL): array {
    $uris = [];
    $masterExporter = $this->entityExporter->setOptions(['entity' => $this->entity]);
    $exporterToUse = $masterExporter;
    if ($language) {
      $slaveExporter = $this->entityExporterPluginManager->createDefaultInstance();
      $slaveExporter->makeSlaveOf($masterExporter)
        ->setOptions([
          'entity' => $this->entity,
          'language' => $this->entity->language(),
        ]);
      $exporterToUse = $slaveExporter;
    }
    foreach ($exporterToUse->getVariantKeys() as $variantKey) {
      $uris[] = $exporterToUse
        ->setOptions(
          [
            'entity' => $this->entity,
            'variant' => $variantKey,
            'language' => $language,
          ]
        )
        ->getUri();
    }

    // Remove null values by using array_filter.
    return array_filter($uris);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getTranslationUris(): array {
    $uris = [];
    $masterExporter = $this->entityExporter->setOptions(['entity' => $this->entity]);
    $slaveExporter = $this->entityExporterPluginManager->createDefaultInstance();
    $slaveExporter->makeSlaveOf($masterExporter);
    foreach ($masterExporter->getTranslationLanguages() as $translationLanguage) {
      $uris[] = $slaveExporter
        ->setOptions([
          'entity' => $this->entity,
          'language' => $translationLanguage,
        ])
        ->getUri();
      // Variants from translations must also be added to the result.
      $translationVariants = $this->getVariantUris($translationLanguage);
      foreach ($translationVariants as $translationVariant) {
        $uris[] = $translationVariant;
      }
    }
    // Remove null values by using array_filter.
    return array_filter($uris);
  }

}
