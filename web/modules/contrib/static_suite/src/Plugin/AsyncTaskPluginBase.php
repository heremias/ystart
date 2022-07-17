<?php

namespace Drupal\static_suite\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\static_suite\Cli\CliCommandFactoryInterface;
use Drupal\static_suite\StaticSuiteException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for plugins that execute any generic asynchronous task.
 */
abstract class AsyncTaskPluginBase extends PluginBase implements AsyncTaskPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The CLI command factory.
   *
   * @var \Drupal\static_suite\Cli\CliCommandFactoryInterface
   */
  protected $cliCommandFactory;

  /**
   * Constructs a AsyncTaskPluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\static_suite\Cli\CliCommandFactoryInterface $cliCommandFactory
   *   The CLI command factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CliCommandFactoryInterface $cliCommandFactory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cliCommandFactory = $cliCommandFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("static_suite.cli_command_factory"),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCwd(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnv(): ?array {
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * On a CLI, don't fork a new process.
   */
  public function isAsync(): bool {
    return PHP_SAPI !== 'cli';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function init(): void {
    if ($this->isAsync()) {
      $this->fork();
    }
    else {
      $this->run();
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function fork(): void {
    $forkLog = $this->getForkLogPath();
    if ($forkLog) {
      $command = $this->getCommand() . ' > ' . $forkLog . ' 2>&1 &';
    }
    else {
      $command = $this->getCommand() . ' &';
    }
    $cliCommand = $this->cliCommandFactory->create($command, $this->getCwd(), $this->getEnv());
    $cliCommandResult = $cliCommand->execute();
    if ($cliCommandResult->getReturnCode() !== 0) {
      throw new StaticSuiteException("Unable to fork build process:\n" . $cliCommandResult->getStdOut() . $cliCommandResult->getStdErr());
    }
  }

}
