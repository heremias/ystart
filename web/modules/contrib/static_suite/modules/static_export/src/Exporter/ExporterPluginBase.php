<?php

namespace Drupal\static_export\Exporter;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\static_export\Event\StaticExportEvent;
use Drupal\static_export\Event\StaticExportEvents;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Drupal\static_export\File\FileCollection;
use Drupal\static_export\File\FileCollectionGroup;
use Drupal\static_export\File\FileCollectionWriter;
use Drupal\static_export\File\FileItem;
use Drupal\static_suite\Entity\EntityUtilsInterface;
use Drupal\static_suite\Language\LanguageContextInterface;
use Drupal\static_suite\StaticSuiteException;
use Drupal\static_suite\StaticSuiteUserException;
use Drupal\static_suite\Utility\BenchmarkTrait;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;
use Drupal\static_suite\Utility\UniqueIdHelperInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base exporter for all exporter plugins.
 */
abstract class ExporterPluginBase extends PluginBase implements ExporterPluginInterface {

  use LoggerChannelTrait;
  use BenchmarkTrait;

  /**
   * Type of operation being done.
   *
   * One of ExporterInterface::OPERATION_*.
   *
   * @var string
   */
  protected $operation;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $eventDispatcher;

  /**
   * Output formatter manager.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected $outputFormatterManager;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Disk writer.
   *
   * @var \Drupal\static_export\File\FileCollectionWriter
   */
  protected $fileCollectionWriter;

  /**
   * Entity Utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtilsInterface
   */
  protected $entityUtils;

  /**
   * Static Suite utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected $staticSuiteUtils;

  /**
   * The language context service.
   *
   * @var \Drupal\static_suite\Language\LanguageContextInterface
   */
  protected $languageContext;

  /**
   * Unique ID helper.
   *
   * @var \Drupal\static_suite\Utility\UniqueIdHelperInterface
   */
  protected $uniqueIdHelper;

  /**
   * Factory for creating ExporterOutputConfigInterface objects.
   *
   * It must be previously configured for each exported type, and instantiated
   * in the base abstract exporter of each type (entity, config, etc)
   *
   * @var \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface
   */
  protected $exporterOutputConfigFactory;

  /**
   * Final FileCollectionGroup the exporter returns after doing all its work.
   *
   * @var \Drupal\static_export\File\FileCollectionGroup
   */
  protected $resultingFileCollectionGroup;

  /**
   * The FileCollectionGroup from the exporters stack.
   *
   * @var \Drupal\static_export\File\FileCollectionGroup
   */
  protected FileCollectionGroup $stackFileCollectionGroup;

  /**
   * Flag for knowing if this is a master export (the first one)
   *
   * @var bool
   */
  protected $isMasterExport = TRUE;

  /**
   * Options array.
   *
   * @var array
   */
  protected $options;

  /**
   * Standalone mode flag.
   *
   * @var bool
   */
  protected $isStandalone = FALSE;

  /**
   * Log To File flag.
   *
   * @var bool
   */
  protected $logToFile = TRUE;

  /**
   * Lock flag.
   *
   * @var bool
   */
  protected $lock = TRUE;

  /**
   * Build flag.
   *
   * @var bool
   */
  protected $mustRequestBuild = FALSE;

  /**
   * Deploy flag.
   *
   * @var bool
   */
  protected $mustRequestDeploy = FALSE;

  /**
   * Output config data.
   *
   * @var \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface
   */
  protected $outputConfig;

  /**
   * Time start.
   *
   * @var float
   */
  protected $timeStart;

  /**
   * Time end.
   *
   * @var float
   */
  protected $timeEnd;

  /**
   * Unique identifier.
   *
   * @var string
   */
  protected $uniqueId;

  /**
   * Flag to indicate that this exporter should always write.
   *
   * @var bool
   */
  protected $isForceWrite = FALSE;

  /**
   * Data set by handle Resolver.
   *
   * @var mixed
   */
  protected $dataFromResolver;

  /**
   * Item (entity, config object, or any data) that is being exported.
   *
   * @var mixed
   */
  protected $exporterItem;

  /**
   * The console output object.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $consoleOutput;

  /**
   * Drupal file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Whether this operation is stacked along other export operations.
   *
   * @var bool
   */
  protected bool $isStacked = FALSE;

  /**
   * Whether this operation is the first item of a stack.
   *
   * @var bool
   */
  protected bool $isFirstStackItem = FALSE;

  /**
   * Whether this operation is the first item of a stack.
   *
   * @var bool
   */
  protected bool $isLastStackItem = FALSE;

  /**
   * Constructor for exporter.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   Output formatter manager.
   * @param \Drupal\static_suite\Entity\EntityUtilsInterface $entity_utils
   *   Entity utils service.
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $static_suite_utils
   *   Static Suite utils.
   * @param \Drupal\static_suite\Language\LanguageContextInterface $languageContext
   *   The language context service.
   * @param \Drupal\static_export\File\FileCollectionWriter $file_collection_writer
   *   Disk Writer.
   * @param \Drupal\static_suite\Utility\UniqueIdHelperInterface $unique_id_helper
   *   Unique ID helper.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $fileSystem,
    LanguageManagerInterface $languageManager,
    EventDispatcherInterface $event_dispatcher,
    OutputFormatterPluginManagerInterface $outputFormatterManager,
    EntityUtilsInterface $entity_utils,
    StaticSuiteUtilsInterface $static_suite_utils,
    LanguageContextInterface $languageContext,
    FileCollectionWriter $file_collection_writer,
    UniqueIdHelperInterface $unique_id_helper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->fileSystem = $fileSystem;
    $this->languageManager = $languageManager;
    $this->eventDispatcher = $event_dispatcher;
    $this->outputFormatterManager = $outputFormatterManager;
    $this->entityUtils = $entity_utils;
    $this->staticSuiteUtils = $static_suite_utils;
    $this->languageContext = $languageContext;
    $this->fileCollectionWriter = $file_collection_writer;
    $this->uniqueIdHelper = $unique_id_helper;
    $this->resultingFileCollectionGroup = new FileCollectionGroup();
    $this->stackFileCollectionGroup = new FileCollectionGroup();
  }

  /**
   * {@inheritdoc}
   *
   * If a exporter needs extra dependencies not defined in this method, define
   * the in setExtraDependencies() method.
   * }
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("config.factory"),
      $container->get("file_system"),
      $container->get("language_manager"),
      $container->get("event_dispatcher"),
      $container->get("plugin.manager.static_output_formatter"),
      $container->get("static_suite.entity_utils"),
      $container->get("static_suite.utils"),
      $container->get("static_suite.language_context"),
      $container->get("static_export.file_collection_writer"),
      $container->get("static_suite.unique_id_helper")
    );
    $instance->setExtraDependencies($container);
    return $instance;

  }

  /**
   * Set extra dependencies.
   *
   * A helper method for exporter plugins to define their own dependencies.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   */
  protected function setExtraDependencies(ContainerInterface $container): void {
    // Each plugin should define here its own extra dependencies.
  }

  /**
   * Get unique id.
   *
   * @return string
   *   The unique id.
   */
  public function uniqueId() {
    return $this->uniqueId;
  }

  /**
   * Set unique id.
   *
   * Should be used with caution.
   *
   * @param string $uniqueId
   *   A unique id.
   */
  public function setUniqueId(string $uniqueId) {
    $this->uniqueId = $uniqueId;
  }

  /**
   * {@inheritdoc}
   */
  public function setConsoleOutput(OutputInterface $output): void {
    $this->consoleOutput = $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getConsoleOutput(): ?OutputInterface {
    return $this->consoleOutput;
  }

  /**
   * Gets whether this export is a master export.
   *
   * @return bool
   *   True or false.
   */
  public function isMasterExport() {
    return $this->isMasterExport;
  }

  /**
   * {@inheritdoc}
   */
  public function setStacked(bool $stacked): ExporterPluginInterface {
    $this->isStacked = $stacked;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isStacked(): bool {
    return $this->isStacked;
  }

  /**
   * {@inheritdoc}
   */
  public function setIsFirstStackItem(bool $isFirstStackItem): ExporterPluginInterface {
    $this->isFirstStackItem = $isFirstStackItem;
    if ($isFirstStackItem) {
      $this->isLastStackItem = FALSE;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isFirstStackItem(): bool {
    return $this->isFirstStackItem;
  }

  /**
   * {@inheritdoc}
   */
  public function setIsLastStackItem(bool $isLastStackItem): ExporterPluginInterface {
    $this->isLastStackItem = $isLastStackItem;
    if ($isLastStackItem) {
      $this->isFirstStackItem = FALSE;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isLastStackItem(): bool {
    return $this->isLastStackItem;
  }

  /**
   * {@inheritdoc}
   */
  public function preProcessOptions(array $options): array {
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function checkParams(array $options): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options): ExporterPluginInterface {
    $options = $this->preProcessOptions($options);
    $this->dispatchEvent(StaticExportEvents::CHECK_PARAMS_START);
    if ($this->checkParams($options)) {
      $this->dispatchEvent(StaticExportEvents::CHECK_PARAMS_END);
      $this->options = $options;
      $this->outputConfig = NULL;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperation(): string {
    return $this->operation;
  }

  /**
   * Set exporter standalone mode.
   *
   * This sets a flag that event subscribers should honor to avoid
   * generation of sub fileCollectionGroups. This is useful when an export
   * operation would trigger other sub exports and you don't want them.
   *
   * This flag is useless if event subscribers don't honor it.
   *
   * @param bool $isStandalone
   *   Standalone flag.
   */
  protected function setIsStandalone(bool $isStandalone) {
    $this->logMessage("Setting \$isStandalone = " . ($isStandalone ? "TRUE" : "FALSE"));
    $this->isStandalone = $isStandalone;
  }

  /**
   * Get stdout flag.
   *
   * @return bool
   *   Boolean flag.
   */
  public function isStandalone() {
    return $this->isStandalone;
  }

  /**
   * Set whether exporter must log to a file.
   *
   * @param bool $logToFile
   *   Log to file flag.
   */
  protected function setLogToFile(bool $logToFile) {
    $this->logToFile = $logToFile;
    $this->logMessage("Setting \$logToFile = " . ($logToFile ? "TRUE" : "FALSE"));
  }

  /**
   * Get whether exporter must log to a file.
   *
   * @return bool
   *   Boolean flag.
   */
  public function mustLogToFile() {
    return $this->logToFile;
  }

  /**
   * Set whether exporter must enable lock mode.
   *
   * @param bool $lock
   *   Lock mode flag.
   */
  protected function setLock(bool $lock) {
    $this->lock = $lock;
    $this->logMessage("Setting \$lock = " . ($lock ? "TRUE" : "FALSE"));
  }

  /**
   * Get lock mode flag.
   *
   * @return bool
   *   Boolean flag.
   */
  public function isLock() {
    return $this->lock;
  }

  /**
   * {@inheritdoc}
   */
  public function mustRequestBuild(): bool {
    return $this->mustRequestBuild;
  }

  /**
   * {@inheritdoc}
   */
  public function setMustRequestBuild(bool $flag) {
    $this->mustRequestBuild = $flag;
  }

  /**
   * {@inheritdoc}
   */
  public function mustRequestDeploy(): bool {
    return $this->mustRequestDeploy;
  }

  /**
   * {@inheritdoc}
   */
  public function setMustRequestDeploy(bool $flag) {
    $this->mustRequestDeploy = $flag;
  }

  /**
   * Get the item being exported.
   *
   * This method is not called "getExporterEntity" since it's used by all
   * exporters, and lots of them don't export entities. So we use a more generic
   * "item" to name them.
   *
   * @return mixed
   *   Exported item.
   */
  abstract public function getExporterItem();

  /**
   * Get an id for the item being exported.
   *
   * @return string
   *   Exported item id.
   */
  abstract public function getExporterItemId();

  /**
   * Get a label for the item being exported.
   *
   * @return string
   *   Exported item label.
   */
  abstract public function getExporterItemLabel();

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->operation = NULL;
    $this->timeStart = NULL;
    $this->timeEnd = NULL;
    $this->isMasterExport = TRUE;
    $this->isStacked = FALSE;
    $this->isFirstStackItem = FALSE;
    $this->isLastStackItem = FALSE;
    $this->isStandalone = FALSE;
    $this->options = NULL;
    $this->outputConfig = NULL;
    $this->logToFile = TRUE;
    $this->lock = TRUE;
    $this->mustRequestBuild = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function makeSlaveOf(ExporterPluginInterface $masterExporter): ExporterPluginInterface {
    $this->reset();
    $this->uniqueId = $masterExporter->uniqueId();
    $this->isMasterExport = FALSE;
    // Slave exporters do not have any reference to stacks.
    $this->isStacked = FALSE;
    $this->isFirstStackItem = FALSE;
    $this->isLastStackItem = FALSE;
    $this->isStandalone = TRUE;
    $this->logToFile = $masterExporter->mustLogToFile();
    $this->lock = $masterExporter->isLock();
    $this->mustRequestBuild = FALSE;
    return $this;
  }

  /**
   * Tells whether user running this process is valid.
   *
   * @param string $cliUser
   *   CLI username.
   *
   * @return bool
   *   TRUE if it's valid, FALSE otherwise.
   */
  public function isValidCliUser(string $cliUser): bool {
    // Ensure it is executed by a valid user on CLI.
    if ($this->staticSuiteUtils->isRunningOnCli()) {
      $allowedUsers = $this->configFactory->get('static_suite.settings')
        ->get('cli_allowed_users');
      if (count($allowedUsers) > 0 && !in_array($cliUser, $allowedUsers, TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function write(array $options = [], bool $isStandalone = FALSE, bool $logToFile = TRUE, bool $lock = TRUE) {
    return $this->export($options, $isStandalone, $logToFile, $lock);
  }

  /**
   * {@inheritdoc}
   */
  public function export(array $options = [], bool $isStandalone = FALSE, bool $logToFile = TRUE, bool $lock = TRUE) {
    if ($this->isStacked) {
      $this->resultingFileCollectionGroup = $this->stackFileCollectionGroup;
    }

    if (($this->isStacked && $this->isFirstStackItem) || (!$this->isStacked && $this->isMasterExport)) {
      $this->dispatchEvent(StaticExportEvents::CHAINED_STEP_START);
    }

    // Ensure it is executed by a valid user on CLI.
    $currentUser = posix_getpwuid(posix_geteuid())['name'];
    if (!$this->isValidCliUser($currentUser)) {
      if (($this->staticSuiteUtils->isInteractiveTty() && $this->configFactory->get('static_suite.settings')
        ->get('cli_throw_exception_interactive_tty')) ||
        (!$this->staticSuiteUtils->isInteractiveTty() && $this->configFactory->get('static_suite.settings')
          ->get('cli_throw_exception_non_interactive_tty'))) {
        throw new StaticSuiteUserException('User "' . $currentUser . '" is not allowed to execute this process on CLI.');
      }
      return $this->resultingFileCollectionGroup;
    }

    // If a variant is present, ensure it's not executed inside a master export.
    if ($this->isMasterExport && !empty($options['variant'])) {
      throw new StaticSuiteUserException('Exporting a variant outside of a child export process is not allowed. Found "' . $options['variant'] . '" variant.');
    }

    try {
      // Early check for opting out of this process.
      $event = $this->dispatchEvent(StaticExportEvents::PREFLIGHT);
      if ($event->mustAbort()) {
        return $this->resultingFileCollectionGroup;
      }

      // Initialize.
      if ($this->isStacked) {
        $this->uniqueId = $this->uniqueIdHelper->getUniqueId();
      }
      elseif ($this->isMasterExport) {
        $this->uniqueId = $this->uniqueIdHelper->generateUniqueId();
      }
      $this->startBenchmark();
      $this->operation = ExporterPluginInterface::OPERATION_WRITE;

      // Enable/disable options as requested.
      $this->setLogToFile($logToFile);
      $this->setIsStandalone($isStandalone);
      $this->setLock($lock);

      // Start logging.
      $this->logMessage("Exporter " . get_class($this) . " started with UNIQUE ID " . $this->uniqueId());
      $this->logMessage("OPERATION: " . $this->operation);
      $this->logMessage("Received \$options: \n" . $this->optionsForLogging($options));
      $this->logMessage("Received \$isStandalone = " . ($isStandalone ? "TRUE" : "FALSE"));
      $this->logMessage("Received \$logToFile = " . ($logToFile ? "TRUE" : "FALSE"));
      $this->logMessage("Received \$lock = " . ($lock ? "TRUE" : "FALSE"));
      $this->logMessage("Received \$this->mustRequestBuild = " . ($this->mustRequestBuild ? "TRUE" : "FALSE"));

      $this->dispatchEvent(StaticExportEvents::START);

      $this->logMessage("Setting params.");
      $this->setOptions($options);
      $this->logMessage("Setting params done.");

      // Check if received item is exportable.
      if (!$this->isExportable($this->getOptions())) {
        throw new StaticSuiteUserException("Item '" . $this->getExporterItemId() . "' ('" . $this->getExporterItemLabel() . "') is not exportable.");
      }

      $calculatedOutputConfig = $this->calculateOutputConfig();
      // Early opt-out if no config is defined.
      if ($calculatedOutputConfig !== NULL) {
        $this->outputConfig = $calculatedOutputConfig;

        // Save a pointer in FileCollectionWriter queue system to ensure
        // concurrent exports are committed to data directory in order.
        if (($this->isStacked && $this->isFirstStackItem) || (!$this->isStacked && $this->isMasterExport)) {
          $this->fileCollectionWriter->setExporter($this);
          // Only insert into the queue if $lock is true.
          if ($lock) {
            $this->logMessage("Queue insertion starting...");
            $this->fileCollectionWriter->startQueueInsertion();
            $this->logMessage("Queue insertion started.");
          }
        }

        $dataToExport = $this->handleResolver();
        if (is_string($dataToExport)) {
          $formattedData = $dataToExport;
        }
        else {
          $formattedData = $this->handleFormatter($dataToExport);
        }
        $this->resultingFileCollectionGroup = $this->handleOutput($formattedData);
      }

      $this->dispatchEvent(StaticExportEvents::END);
      $this->endBenchmark();
    }
    catch (\Throwable $e) {
      // This is Throwable to capture any kind of exception and ensure
      // that any pending queue insertion is deleted.
      $this->fileCollectionWriter->deletePendingQueueInsertion();

      // Log the error to the file log.
      $this->logMessage($e->getMessage() . "\n" . $e->getTraceAsString());

      // Send an error to logger.
      // Do not log anything when running on an interactive TTY.
      if (!$this->staticSuiteUtils->isRunningOnCli() || !$this->staticSuiteUtils->isInteractiveTty()) {
        $message = $e->getMessage() . ' Please, review ' . $this->configFactory->get('static_export.settings')
          ->get('log_dir') . '/' . $this->uniqueId . '.log';
        $this->getLogger('static_export')->error($message);
      }

      // Rethrow StaticSuiteUserException.
      if ($e instanceof StaticSuiteUserException) {
        throw new StaticSuiteUserException($e->getMessage(), $e->getCode(), $e);
      }

      // Any other exception is rethrown as StaticSuiteException.
      throw new StaticSuiteException($e);
    }

    if (($this->isStacked && $this->isLastStackItem) || (!$this->isStacked && $this->isMasterExport)) {
      $this->dispatchEvent(StaticExportEvents::CHAINED_STEP_END);
    }

    return $this->resultingFileCollectionGroup;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove this method to avoid duplicated code.
   */
  public function delete(array $options = [], bool $isStandalone = FALSE, bool $logToFile = TRUE, bool $lock = TRUE) {
    if ($this->isStacked) {
      $this->resultingFileCollectionGroup = $this->stackFileCollectionGroup;
    }

    $this->dispatchEvent(StaticExportEvents::CHAINED_STEP_START);

    // Ensure it is executed by a valid user on CLI.
    $currentUser = posix_getpwuid(posix_geteuid())['name'];
    if (!$this->isValidCliUser($currentUser)) {
      if (($this->staticSuiteUtils->isInteractiveTty() && $this->configFactory->get('static_suite.settings')
        ->get('cli_throw_exception_interactive_tty')) ||
        (!$this->staticSuiteUtils->isInteractiveTty() && $this->configFactory->get('static_suite.settings')
          ->get('cli_throw_exception_non_interactive_tty'))) {
        throw new StaticSuiteUserException('User "' . $currentUser . '" is not allowed to execute this process on CLI.');
      }
      return $this->resultingFileCollectionGroup;
    }

    try {
      // Early check for opting out of this process.
      $event = $this->dispatchEvent(StaticExportEvents::PREFLIGHT);
      if ($event->mustAbort()) {
        return $this->resultingFileCollectionGroup;
      }

      // Initialize.
      if ($this->isStacked) {
        $this->uniqueId = $this->uniqueIdHelper->getUniqueId();
      }
      elseif ($this->isMasterExport) {
        $this->uniqueId = $this->uniqueIdHelper->generateUniqueId();
      }
      $this->startBenchmark();
      $this->operation = ExporterPluginInterface::OPERATION_DELETE;

      // Enable/disable options as requested.
      $this->setLogToFile($logToFile);
      $this->setIsStandalone($isStandalone);
      $this->setLock($lock);

      // Start logging.
      $this->logMessage("Exporter " . get_class($this) . " started with UNIQUE ID " . $this->uniqueId());
      $this->logMessage("OPERATION: " . $this->operation);
      $this->logMessage("Received \$options: \n" . $this->optionsForLogging($options));
      $this->logMessage("Received \$isStandalone = " . ($isStandalone ? "TRUE" : "FALSE"));
      $this->logMessage("Received \$logToFile = " . ($logToFile ? "TRUE" : "FALSE"));
      $this->logMessage("Received \$lock = " . ($lock ? "TRUE" : "FALSE"));
      $this->logMessage("Received \$this->mustRequestBuild = " . ($this->mustRequestBuild ? "TRUE" : "FALSE"));

      $this->dispatchEvent(StaticExportEvents::START);

      $this->logMessage("Setting params.");
      $this->setOptions($options);
      $this->logMessage("Setting params done.");

      $calculateOutputConfig = $this->calculateOutputConfig();
      // Early opt-out if no config is defined.
      if ($calculateOutputConfig !== NULL) {
        $this->outputConfig = $calculateOutputConfig;

        // Save a pointer in FileCollectionWriter queue system to ensure
        // concurrent exports are committed to data directory in order.
        if (($this->isStacked && $this->isFirstStackItem) || (!$this->isStacked && $this->isMasterExport)) {
          $this->fileCollectionWriter->setExporter($this);
          // Only insert into the queue if $lock is true.
          if ($lock) {
            $this->logMessage("Queue insertion starting...");
            $this->fileCollectionWriter->startQueueInsertion();
            $this->logMessage("Queue insertion started.");
          }
        }

        $this->resultingFileCollectionGroup = $this->handleOutput();
      }

      $this->dispatchEvent(StaticExportEvents::END);
      $this->endBenchmark();

    }
    catch (\Throwable $e) {
      // This is Throwable to capture any kind of exception and ensure
      // that any pending queue insertion is deleted.
      $this->fileCollectionWriter->deletePendingQueueInsertion();

      // Log the error to the file log.
      $this->logMessage($e->getMessage() . "\n" . $e->getTraceAsString());

      // Send an error to logger.
      // Do not log anything when running on an interactive TTY.
      if (!$this->staticSuiteUtils->isRunningOnCli() || !$this->staticSuiteUtils->isInteractiveTty()) {
        $message = $e->getMessage() . " Please, review " . $this->configFactory->get('static_export.settings')
          ->get('log_dir') . '/' . $this->uniqueId . '.log';
        $this->getLogger('static_export')->error($message);
      }

      // Rethrow StaticSuiteUserException.
      if ($e instanceof StaticSuiteUserException) {
        throw new StaticSuiteUserException($e->getMessage(), $e->getCode(), $e);
      }

      // Any other exception is rethrown as StaticSuiteException.
      throw new StaticSuiteException($e->getMessage(), $e->getCode(), $e);
    }

    if (($this->isStacked && $this->isLastStackItem) || (!$this->isStacked && $this->isMasterExport)) {
      $this->dispatchEvent(StaticExportEvents::CHAINED_STEP_END);
    }

    return $this->resultingFileCollectionGroup;
  }

  /**
   * {@inheritdoc}
   */
  public function getResultingFileCollectionGroup(): FileCollectionGroup {
    return $this->resultingFileCollectionGroup;
  }

  /**
   * {@inheritdoc}
   */
  public function setStackFileCollectionGroup(FileCollectionGroup $stackFileCollectionGroup): ExporterPluginInterface {
    $this->stackFileCollectionGroup = $stackFileCollectionGroup;
    return $this;
  }

  /**
   * Get output config data from a specific exporter.
   *
   * Variants are not defined here but in calculateOutputConfig().
   *
   * @return \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface|null
   *   Output config data.
   */
  abstract protected function getOutputDefinition(): ?ExporterOutputConfigInterface;

  /**
   * Get exported data from the specific resolver of a exporter.
   *
   * @return array|string
   *   Exported data.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  abstract protected function calculateDataFromResolver();

  /**
   * Calculate output config.
   *
   * @return \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface|null
   *   Output config data.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function calculateOutputConfig(): ?ExporterOutputConfigInterface {
    $outputConfig = $this->dispatchEvent(StaticExportEvents::CONFIG_START)
      ->getOutputConfig();
    if (!$outputConfig) {
      $outputConfig = $this->getOutputDefinition();
    }
    $outputConfig = $this->dispatchEvent(StaticExportEvents::CONFIG_END, [StaticExportEvent::EVENT_CONFIG => $outputConfig])
      ->getOutputConfig();

    // If a variant is present, we ensure it's added at the end of the filename.
    // It's done here because variants are available for all exporters.
    if (!empty($this->options['variant'])) {
      $originalFilename = $outputConfig->getDefinition()
        ->getPath()
        ->getFilename();
      $outputConfig->getDefinition()
        ->getPath()
        ->setFilename($originalFilename . ExporterPluginInterface::VARIANT_SEPARATOR . $this->options['variant']);
    }

    return $outputConfig;
  }

  /**
   * Handles the process of getting export data using a specific resolver.
   *
   * @return array|string
   *   Data to be exported.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function handleResolver() {
    $this->logMessage("Handling resolver.");
    $event = $this->dispatchEvent(StaticExportEvents::RESOLVER_START);
    $this->dataFromResolver = $event->getDataFromResolver();
    // If RESOLVER_START event returns something, skip executing
    // calculateDataFromResolver()
    if (!$this->dataFromResolver) {
      $this->dataFromResolver = $this->calculateDataFromResolver();
    }
    $event = $this->dispatchEvent(StaticExportEvents::RESOLVER_END, [StaticExportEvent::EVENT_DATA_FROM_RESOLVER => $this->dataFromResolver]);
    $this->dataFromResolver = $event->getDataFromResolver();
    $this->logMessage("Handling resolver done.");
    return $this->dataFromResolver;
  }

  /**
   * Gets DataFromResolver.
   *
   * @todo Ensure this method is really needed.
   */
  public function getDataFromResolver() {
    return $this->dataFromResolver;
  }

  /**
   * Handles data formatting.
   *
   * @param array $data
   *   Entity data.
   *
   * @return string
   *   Formatted entity data.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function handleFormatter(array $data) {
    $this->logMessage("Handling formatter.");
    $this->dispatchEvent(StaticExportEvents::FORMATTER_START);

    // Allow exporters to bypass formatting by adding a special key to the data
    // array.
    if (isset($data[ExporterPluginInterface::OVERRIDE_FORMAT])) {
      $dataFromFormatter = $data[ExporterPluginInterface::OVERRIDE_FORMAT];
    }
    else {
      $formatId = $this->outputConfig->getDefinition()->getFormat();
      if (empty($formatId)) {
        throw new StaticSuiteUserException("No output export format defined for this data.");
      }

      try {
        $outputFormatter = $this->outputFormatterManager->getInstance(['plugin_id' => $formatId]);
      }
      catch (PluginException $e) {
        throw new StaticSuiteUserException("Unknown Static Export output format: " . $formatId);
      }
      $dataFromFormatter = $outputFormatter->format($data);
    }

    $event = $this->dispatchEvent(StaticExportEvents::FORMATTER_END, [StaticExportEvent::EVENT_DATA_FROM_FORMATTER => $dataFromFormatter]);
    $dataFromFormatter = $event->getDataFromFormatter();

    $this->logMessage("Handling formatter done.");
    return $dataFromFormatter;
  }

  /**
   * Handles data output.
   *
   * @param string $formattedData
   *   The data to be saved.
   *
   * @return \Drupal\static_export\File\FileCollectionGroup
   *   A FileCollectionGroup. It could be more than one FileCollection because
   *   the fileCollectionWriter manages a queue.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function handleOutput(string $formattedData = "") {
    $this->logMessage("Handling output.");
    $this->dispatchEvent(StaticExportEvents::OUTPUT_START);

    // Get all data for the FileItem.
    $fileCollection = NULL;
    if ($this->isStacked) {
      // This can return null if this is the first execution in the stack.
      $fileCollection = $this->resultingFileCollectionGroup->getFirstFileCollection();
    }
    if (!$fileCollection) {
      $fileCollection = new FileCollection($this->uniqueId);
    }
    $fileOperation = ($this->operation === ExporterPluginInterface::OPERATION_WRITE) ? FileItem::OPERATION_WRITE : FileItem::OPERATION_DELETE;
    $uri = $this->getUri();
    $exporterItem = $this->getExporterItem();
    $currentStatus = 1;
    $oldStatus = 1;
    if ($exporterItem instanceof EditorialContentEntityBase) {
      $currentStatus = $exporterItem->isPublished() ? 1 : 0;
      $originalExporterItem = $exporterItem->original ?? NULL;
      if (is_object($originalExporterItem) && $originalExporterItem instanceof EditorialContentEntityBase) {
        if ($originalExporterItem->isTranslatable()) {
          $langcode = $exporterItem->language()->getId();
          $availableTranslationLanguages = $originalExporterItem->getTranslationLanguages(TRUE);
          if (isset($availableTranslationLanguages[$langcode])) {
            $originalExporterItem = $originalExporterItem->getTranslation($langcode);
          }
        }
        $oldStatus = $originalExporterItem->isPublished() ? 1 : 0;
      }
      else {
        // When previewing a node using "static_build.preview" route, no
        // original entity is available, since no save has been done. In this
        // case, no status has changed, so keep $oldStatus and $currentStatus
        // the same.
        $oldStatus = $currentStatus;
      }
    }
    $fileCollection->addFileItem(new FileItem(
      $fileOperation,
      $currentStatus,
      $oldStatus,
      $uri,
      $formattedData,
      $this->getExporterItemId(),
      $this->getExporterItemLabel(),
      // Call getBenchmark() here, before dispatching any event,
      // to get real data for this file.
      $this->getBenchmark()
    ));

    // Export variants.
    $fileCollection = $this->dispatchEvent(StaticExportEvents::VARIANTS_EXPORT_START, [StaticExportEvent::EVENT_FILE_COLLECTION => $fileCollection])
      ->getFileCollection();
    $variantsFileCollection = $this->exportVariants();
    $variantsFileCollection = $this->dispatchEvent(StaticExportEvents::VARIANTS_EXPORT_END, [StaticExportEvent::EVENT_FILE_COLLECTION => $variantsFileCollection])
      ->getFileCollection();
    $fileCollection->merge($variantsFileCollection);

    // Export translations.
    $fileCollection = $this->dispatchEvent(StaticExportEvents::TRANSLATIONS_EXPORT_START, [StaticExportEvent::EVENT_FILE_COLLECTION => $fileCollection])
      ->getFileCollection();
    $translationsFileCollection = $this->exportTranslations();
    $translationsFileCollection = $this->dispatchEvent(StaticExportEvents::TRANSLATIONS_EXPORT_END, [StaticExportEvent::EVENT_FILE_COLLECTION => $translationsFileCollection])
      ->getFileCollection();
    $fileCollection->merge($translationsFileCollection);

    $event = $this->dispatchEvent(StaticExportEvents::WRITE_START, [StaticExportEvent::EVENT_FILE_COLLECTION => $fileCollection]);
    $fileCollection = $event->getFileCollection();

    // Initialize $fileCollectionGroup.
    $fileCollectionGroup = new FileCollectionGroup($fileCollection);

    // Only write data if this is the master export (the first one)
    if ((
        ($this->isStacked && $this->isLastStackItem) ||
        (!$this->isStacked && $this->isMasterExport)
      ) &&
      $fileCollection->size() > 0
    ) {
      // We get an array of FileCollection because fileCollectionWriter
      // processes a queue.
      $fileCollectionGroup = $this->fileCollectionWriter->save($fileCollection);
    }

    $fileCollectionGroup = $this->dispatchEvent(StaticExportEvents::WRITE_END, [StaticExportEvent::EVENT_FILE_COLLECTION_GROUP => $fileCollectionGroup])
      ->getFileCollectionGroup();
    $fileCollectionGroup = $this->dispatchEvent(StaticExportEvents::OUTPUT_END, [StaticExportEvent::EVENT_FILE_COLLECTION_GROUP => $fileCollectionGroup])
      ->getFileCollectionGroup();

    $this->logMessage("Handling output done.");
    return $fileCollectionGroup;
  }

  /**
   * Exports variants.
   *
   * Always export variants unless this is already a variant export.
   *
   * @return \Drupal\static_export\File\FileCollection
   *   A file collection.
   */
  protected function exportVariants(): FileCollection {
    return new FileCollection($this->uniqueId());
  }

  /**
   * Exports translations.
   *
   * Export only when this is a master export.
   *
   * This is the default implementation for all exporters, which does not export
   * anything. Each exporter is in charge of executing its own translation
   * export strategy, if any.
   *
   * For example, entityExporter and configExporter does export translations,
   * while localeExporter does not.
   *
   * @return \Drupal\static_export\File\FileCollection
   *   A file collection.
   */
  protected function exportTranslations(): FileCollection {
    return new FileCollection($this->uniqueId());
  }

  /**
   * {@inheritdoc}
   */
  public function getUri(): ?UriInterface {
    if (!$this->outputConfig) {
      $this->outputConfig = $this->calculateOutputConfig();
    }
    return $this->outputConfig ? $this->outputConfig->uri() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function logMessage(string $message): void {
    if ($this->logToFile && $this->uniqueId()) {
      $logFile = $this->configFactory->get('static_export.settings')
        ->get('log_dir') . "/" . $this->uniqueId() . '.log';
      $timeStamp = $this->staticSuiteUtils->getFormattedMicroDate("Y-m-d H:i:s.u");
      $indent = $this->isMasterExport ? "" : "[sub-export] ";
      $line = "[$timeStamp] $indent$message\n";
      $logDir = $this->fileSystem->dirname($logFile);
      if (!file_exists($logDir)) {
        $this->fileSystem->mkdir($this->fileSystem->dirname($logFile), 0777, TRUE);
      }
      file_put_contents($logFile, $line, FILE_APPEND);
    }
  }

  /**
   * Get logger object.
   *
   * @return \Psr\Log\LoggerInterface
   *   A logger object
   */
  public function logger(): LoggerInterface {
    return $this->getLogger('static_export');
  }

  /**
   * Returns output config.
   *
   * @return \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigInterface
   *   Config data.
   */
  public function getOutputConfig(): ExporterOutputConfigInterface {
    return $this->outputConfig;
  }

  /**
   * Dispatch StaticExportEvent for the given tag.
   *
   * @param string $eventName
   *   The name of the event.
   * @param array $data
   *   Data for the event.
   *
   * @return \Drupal\static_export\Event\StaticExportEvent
   *   The event.
   */
  public function dispatchEvent(string $eventName, array $data = []): StaticExportEvent {
    $event = new StaticExportEvent($this);
    $event->setData($data);

    // Dispatch the event.
    $this->logMessage("EVENT DISPATCH '$eventName' TRIGGERED");
    $this->eventDispatcher->dispatch($event, $eventName);
    $this->logMessage("EVENT DISPATCH '$eventName' DONE");

    // Return the event.
    return $event;
  }

  /**
   * Tell whether this exporter should always write.
   *
   * @return bool
   *   True if write is forced.
   */
  public function isForceWrite(): bool {
    return $this->isForceWrite;
  }

  /**
   * Flag to indicate that this exporter should always write.
   *
   * @param bool $isForceWrite
   *   Flag for always write.
   */
  public function setIsForceWrite(bool $isForceWrite) {
    $this->isForceWrite = $isForceWrite;
  }

  /**
   * Print options for logging.
   *
   * @param array $options
   *   Options array to log.
   *
   * @return string
   *   A loggable string.
   */
  private function optionsForLogging(array $options) {
    $loggableString = "";
    foreach ($options as $key => $option) {
      $loggableString .= "$key => ";
      if (is_object($option)) {
        $loggableString .= get_class($option);
        if (method_exists($option, "id")) {
          $loggableString .= " ID: " . $option->id();
        }
      }
      else {
        $loggableString .= print_r($option, TRUE);
      }
      $loggableString .= "\n";
    }
    return "\n" . trim($loggableString);
  }

  /**
   * {@inheritdoc}
   *
   * By default, this method always returns true. Some exporters do not
   * accept any parameter (they export a fixed set of data) so they don't
   * need to check any option.
   */
  public function isExportable(array $options): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariantKeys(): array {
    $variantKeys = $this->getVariantKeyDefinitions();
    return $this->dispatchEvent(StaticExportEvents::VARIANT_KEYS_DEFINED, [StaticExportEvent::EVENT_VARIANT_KEYS => $variantKeys])
      ->getVariantKeys();
  }

  /**
   * Defines the variant keys of a exporter.
   *
   * It's a protected method wrapped in getVariantKeys(), which is in charge of
   * getting these definitions and dispatching events.
   *
   * This method returns an empty array because most exporters don't make use
   * of variants.
   *
   * @return string[]
   *   Array of strings
   */
  protected function getVariantKeyDefinitions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationLanguages(): array {
    $translationLanguages = $this->getTranslationLanguageDefinitions();
    return $this->dispatchEvent(StaticExportEvents::TRANSLATION_KEYS_DEFINED, [StaticExportEvent::EVENT_TRANSLATION_LANGUAGES => $translationLanguages])
      ->getTranslationLanguages();
  }

  /**
   * Defines the translation languages of a exporter.
   *
   * It's a protected method wrapped in getTranslationLanguages(), which is in
   * charge of getting these definitions and dispatching events.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]
   *   Array of languages
   */
  protected function getTranslationLanguageDefinitions(): array {
    return [];
  }

}
