<?php

namespace Drupal\static_export\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the "Exportable Entity" add and edit forms.
 */
class ExportableEntityForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\static_export\Entity\ExportableEntityInterface
   */
  protected $entity;

  /**
   * Entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface
   */
  protected $entityExporterPluginManager;

  /**
   * The static data resolver manager.
   *
   * @var \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface
   */
  protected $dataResolverManager;

  /**
   * The static output formatter manager.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected $outputFormatterManager;

  /**
   * Constructs an StaticExportEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   Entity type bundle info service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory.
   * @param \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface $dataResolverManager
   *   The static data resolver manager.
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   The static output formatter manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, ConfigFactoryInterface $configFactory, DataResolverPluginManagerInterface $dataResolverManager, OutputFormatterPluginManagerInterface $outputFormatterManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->configFactory = $configFactory;
    $this->dataResolverManager = $dataResolverManager;
    $this->outputFormatterManager = $outputFormatterManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('config.factory'),
      $container->get('plugin.manager.static_data_resolver'),
      $container->get('plugin.manager.static_output_formatter')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $exportableEntity = $this->entity;

    $dataResolvers = $this->dataResolverManager->getDefinitions();
    if (count($dataResolvers) === 0) {
      $this->messenger()
        ->addWarning(
          $this->t(
            'In order to be able to export an entity element you must first activate one of the <strong>resolver modules</strong> provided by Static Suite. Please visit the <a href="@url">module list</a>.',
            ['@url' => Url::fromRoute('system.modules_list')->toString()]
          )
        );
      return [];
    }

    $definitions = $this->entityTypeManager->getDefinitions();
    $entityTypes = [];
    foreach ($definitions as $definition) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($definition->id());
      foreach ($bundles as $name => $bundle) {
        $entityTypes[$definition->id()][$definition->id() . '.' . $name] = $bundle['label'];
      }
    }

    if ($exportableEntity->isNew()) {
      $form['id'] = [
        '#type' => 'select',
        '#options' => $entityTypes,
        '#title' => $this->t('Select entity type and bundle'),
        '#default_value' => $exportableEntity->id(),
        '#required' => TRUE,
      ];
    }
    else {

      $form['id_markup'] = [
        '#markup' => '<p><strong>' . $this->t('Exportable entity id') . ':</strong> ' . $exportableEntity->id() . '</p>',
      ];

      $form['id'] = [
        '#type' => 'hidden',
        '#value' => $exportableEntity->id(),
        '#required' => TRUE,
      ];
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $exportableEntity->label(),
      '#description' => $this->t('The human-readable name of this exportable entity.'),
      '#required' => TRUE,
    ];

    $form['export'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('File to export'),
    ];

    $form['export']['resolvers'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Data resolver settings'),
    ];

    $form['export']['resolvers']['header'] = [
      '#markup' => '<p>' . $this->t('Exported data comes from a data resolver. They handle the process of taking an entity and "resolving" it to some exportable data. Some resolvers return raw data (an array) that can be later formatted using the "Export Format" of your choice (see below). Some other resolvers just return data in a specific format that can not be changed.') . '</p><p>' . $this->t('Data resolvers are extensible plugins. If no one matches your needs, you can define your own by creating a plugin with a "@StaticDataResolver" annotation. Please, refer to the documentation for more info.') . '</p>',
    ];

    $dataResolversThatExportRawData = $this->dataResolverManager->getDataResolverIdsThatExportRawData();
    $dataResolversThatExportFormattedData = $this->dataResolverManager->getDataResolverIdsThatExportFormattedData();

    $toOptionValue = static function ($dataResolverId) {
      return ['value' => $dataResolverId];
    };

    $optionsForDataResolversThatExportRawData = array_map($toOptionValue, $dataResolversThatExportRawData);
    $optionsForDataResolversThatExportFormattedData = array_map($toOptionValue, $dataResolversThatExportFormattedData);

    $dataResolverOptions = [];
    $dataResolverDefinitions = $this->dataResolverManager->getDefinitions();
    foreach ($dataResolverDefinitions as $dataResolverDefinition) {
      if (in_array($dataResolverDefinition['id'], $dataResolversThatExportRawData, TRUE)) {
        $dataResolverCapability = $this->t('supports all export formats');
      }
      else {
        $dataResolverCapability = $this->t('supports only @format format', ['@format' => $dataResolverDefinition['format']]);
      }
      $dataResolverOptions[$dataResolverDefinition['id']] = $dataResolverDefinition['label'] . ' (' . $dataResolverCapability . ')';
    }
    $form['export']['resolvers']['data_resolver'] = [
      '#type' => 'select',
      '#title' => $this->t('Data resolver'),
      '#required' => TRUE,
      '#options' => $dataResolverOptions,
      '#default_value' => $exportableEntity->getDataResolver(),
    ];

    $missingResolvers = [];
    if (empty($dataResolverOptions['graphql'])) {
      $missingResolvers[] = 'GraphQL -static_export_data_resolver_graphql-';
    }
    if (empty($dataResolverOptions['jsonapi'])) {
      $missingResolvers[] = 'JSON:API -static_export_data_resolver_jsonapi-';
    }
    if (count($missingResolvers)) {
      $form['export']['resolvers']['missing_info'] = [
        '#markup' => '<p>' . $this->t('Please note that <strong>Static Export provides several data resolvers as modules than you may consider enabling</strong> (' . implode(', ', $missingResolvers) . ') if you need more control over the exported data.') . '</p>',
      ];
    }

    $form['export']['file'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('File settings'),
    ];

    $formatDefinitions = $this->outputFormatterManager->getDefinitions();
    $formatOptions = [];
    foreach ($formatDefinitions as $dataResolverDefinition) {
      $formatOptions[$dataResolverDefinition['id']] = $dataResolverDefinition['label'];
    }

    if (count($optionsForDataResolversThatExportRawData) > 0) {
      $form['export']['file']['format'] = [
        '#type' => 'select',
        '#prefix' => '<p>' . $this->t('Entities are exported to a path using an extension derived from the selected format.'),
        '#title' => $this->t('Export format'),
        '#options' => $formatOptions,
        '#default_value' => $exportableEntity->getFormat(),
        '#description' => $this->t('Format for the exported data.'),
        '#states' => [
          'visible' => [
            ':input[name="data_resolver"]' => $optionsForDataResolversThatExportRawData,
          ],
        ],
      ];
    }

    $form['export']['file']['format_from_resolver'] = [
      '#type' => 'select',
      '#title' => $this->t('Export format'),
      '#options' => [$this->t('Export format defined by the resolver of your choice')],
      '#description' => $this->t('Export format is defined by the resolver of your choice and can not be changed. If you need data to be exported in another format, please chose a resolver that supports it.'),
      '#states' => [
        'visible' => [
          ':input[name="data_resolver"]' => $optionsForDataResolversThatExportFormattedData,
        ],
      ],
    ];

    $form['export']['file']['no_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Export format'),
      '#options' => [$this->t('Please select a data resolver to determine formatting options.')],
      '#description' => $this->t('Please select a data resolver to determine formatting options.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="data_resolver"]' => ['value' => ''],
        ],
      ],
    ];

    $missingFormatters = [];
    if (empty($formatOptions['json'])) {
      $missingFormatters[] = 'JSON -static_export_output_formatter_json-';
    }
    if (empty($formatOptions['xml'])) {
      $missingFormatters[] = 'XML -static_export_output_formatter_xml-';
    }
    if (empty($formatOptions['yaml'])) {
      $missingFormatters[] = 'YAML -static_export_output_formatter_yaml-';
    }
    if (count($missingFormatters)) {
      $form['export']['file']['format_missing_info'] = [
        '#markup' => '<p>' . $this->t('Please note that <strong>Static Export provides several output formatters as modules than you may consider enabling</strong> (' . implode(', ', $missingFormatters) . ') if you need more control over the format of the exported data.') . '</p>',
      ];
    }

    $form['dependencies'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Dependencies'),
    ];

    $form['dependencies']['export_referencing_entities'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('When this entity is exported, export other entities where this entity appears.'),
      '#default_value' => $exportableEntity->getExportReferencingEntities(),
    ];

    $form['dependencies']['recursion_level'] = [
      '#type' => 'number',
      '#title' => $this->t('Recursion level'),
      '#description' => $this->t('How many recursion levels should be taken into account when exporting dependencies. Default value is 1. Given an scenario where a "node 1" appears in "node 2", "node 2" appears in "node 3", and so on, a value of "1" means that exporting "node 1" will also export "node 2", but not "node 3". Use 0 for an infinite recursion. Use this value with caution as it could trigger a export of *all* your contents.'),
      '#default_value' => $exportableEntity->getRecursionLevel(),
      '#min' => 0,
      '#size' => 3,
      '#states' => [
        'visible' => [
          ':input[name="export_referencing_entities"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="export_referencing_entities"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['cli'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('CLI options'),
      '#description' => $this->t('Options for CRUD operations that happens outside a web server (e.g.- a cron or a drush command editing or adding entities, etc)'),
    ];

    $form['cli']['export_when_crud_happens_on_cli'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Export data when a CRUD operation happens on CLI (recommended)'),
      '#default_value' => $exportableEntity->getExportWhenCrudHappensOnCli(),
      '#description' => $this->t('It is recommended to export data from these CRUD operation so there is no mismatch between Drupal database and exported data.'),
      '#required' => FALSE,
    ];

    $form['cli']['request_build_when_crud_exports_on_cli'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Request a build when a CRUD operation exports data on CLI (not recommended).'),
      '#default_value' => $exportableEntity->getRequestBuildWhenCrudExportsOnCli(),
      '#description' => $this->t('It is not recommended to request a build from a non-interactive process, because it will lead to several builds taking place in an uncontrolled way.'),
      '#required' => FALSE,
    ];

    $form['page'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Pages'),
      '#description' => $this->t('When making Drupal static, you need to migrate each dynamically created content (e.g.- content types -node bundles-, taxonomy terms, etc) to its static version. To do so, you first need to export its data and then develop a static page using the SSG (Static Site Generator) of your choice. This process usually takes some days or weeks. In the meanwhile, Drupal continues serving your pages. But then, when that migration process is done, you should come back to this form and enable "It is a statified page" option.'),
    ];

    $form['page']['is_statified_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('It is a statified page'),
      '#default_value' => $exportableEntity->getIsStatifiedPage(),
      '#description' => $this->t("Pages already statified by the Static Builder of your choice are not served by Drupal anymore. This option changes the way Drupal renders and previews your content. It must be enabled so any Static Preview module can work once you have finished migrating a node/taxonomy term, etc to its static version."),
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $dataResolvers = $this->dataResolverManager->getDefinitions();
    if (count($dataResolvers) === 0) {
      return [];
    }
    return parent::actionsElement($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $exportableEntity = $this->entity;

    if ($dataResolverId = $exportableEntity->get('data_resolver')) {
      $dataResolverDefinitions = $this->dataResolverManager->getDefinitions();

      // Check for a valid data resolver.
      if (empty($dataResolverDefinitions[$dataResolverId])) {
        $form_state->setError(
          $form,
          $this->t('Invalid data resolver: "' . $dataResolverId . '"')
        );
      }

      // Check for a valid output formatter.
      if (!empty($dataResolverDefinitions[$dataResolverId]) && empty($dataResolverDefinitions[$dataResolverId]['format'])) {
        $formatId = $exportableEntity->get('format');
        $outputFormatterDefinitions = $this->outputFormatterManager->getDefinitions();
        if (empty($outputFormatterDefinitions[$formatId])) {
          $form_state->setError(
            $form,
            $this->t('Invalid output format: "' . $formatId . '"')
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $exportableEntity = $this->entity;

    // Ensure format is obtained from a data resolver if that resolver enforces
    // a specific format.
    if ($dataResolverId = $exportableEntity->get('data_resolver')) {
      $dataResolverDefinitions = $this->dataResolverManager->getDefinitions();
      if (isset($dataResolverDefinitions[$dataResolverId]) && !empty($dataResolverDefinitions[$dataResolverId]['format'])) {
        $exportableEntity->set('format', $dataResolverDefinitions[$dataResolverId]['format']);
      }
    }

    // Manage dependencies.
    if ($exportableEntity->get('export_referencing_entities')) {
      if ($exportableEntity->get('recursion_level') === '') {
        // Save a default value.
        $exportableEntity->set('recursion_level', 1);
      }
      else {
        // Ensure value is positive.
        $exportableEntity->set('recursion_level', abs($exportableEntity->get('recursion_level')));
      }
    }
    else {
      $exportableEntity->set('recursion_level', NULL);
    }

    $status = NULL;
    try {
      $status = $exportableEntity->save();
    }
    catch (\Exception $e) {
      @trigger_error('Error while saving an exportable entity: ' . $e->getMessage(), E_USER_WARNING);
    }

    if ($status) {
      $this->messenger()
        ->addMessage($this->t('Saved the %label Exportable Entity.', [
          '%label' => $exportableEntity->label(),
        ]));
    }
    else {
      $this->messenger()
        ->addMessage($this->t('The %label Exportable Entity was not saved.', [
          '%label' => $exportableEntity->label(),
        ]), MessengerInterface::TYPE_ERROR);
    }

    $form_state->setRedirect('entity.exportable_entity.collection');
  }

}
