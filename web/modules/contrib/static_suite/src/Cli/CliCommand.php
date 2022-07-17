<?php

namespace Drupal\static_suite\Cli;

use Drupal\static_suite\Cli\Result\CliCommandResultFactoryInterface;
use Drupal\static_suite\Cli\Result\CliCommandResultInterface;
use Drupal\static_suite\StaticSuiteException;

/**
 * A class for CLI commands.
 */
class CliCommand implements CliCommandInterface {

  /**
   * The CLI command result factory.
   *
   * @var \Drupal\static_suite\Cli\Result\CliCommandResultFactoryInterface
   */
  protected CliCommandResultFactoryInterface $cliCommandResultFactory;

  /**
   * The CLI command to execute.
   *
   * @var string
   */
  protected string $cmd;

  /**
   * The working directory in which the call will be executed.
   *
   * @var string|null
   */
  protected ?string $cwd;

  /**
   * Environment variables.
   *
   * @var array|null
   */
  protected ?array $env;

  /**
   * Array of file pointers that correspond to PHP's stdin, stdout and stderr.
   *
   * @var array
   */
  protected array $pipes = [];

  /**
   * A resource representing the process.
   *
   * @var resource
   */
  protected $process;

  /**
   * Creates a new instance of a CLI command.
   *
   * @param \Drupal\static_suite\Cli\Result\CliCommandResultFactoryInterface $cliCommandResultFactory
   *   The CLI command result factory.
   * @param string $cmd
   *   The CLI command to execute.
   * @param string|null $cwd
   *   The initial working directory for the command. This must be an absolute
   *   directory path, or NULL if you want to use the default value (the
   *   working dir of the current PHP process).
   * @param array|null $env
   *   An array with the environment variables for the command that will be
   *   run. It will get merged with the environment of the current PHP process.
   *   Pass null to use the same environment as the current PHP process.
   */
  public function __construct(CliCommandResultFactoryInterface $cliCommandResultFactory, string $cmd, string $cwd = NULL, array $env = NULL) {
    $this->cliCommandResultFactory = $cliCommandResultFactory;
    $this->cmd = $cmd;
    $this->cwd = $cwd;
    $this->env = is_array($env) ? array_merge(getenv(), $env) : getenv();
  }

  /**
   * {@inheritdoc}
   */
  public function getCmd(): string {
    return $this->cmd;
  }

  /**
   * {@inheritdoc}
   */
  public function getCwd(): ?string {
    return $this->cwd;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnv(): ?array {
    return $this->env;
  }

  /**
   * {@inheritdoc}
   */
  public function open() {
    $descriptorSpec = [
      0 => ["pipe", "r"],
      1 => ["pipe", "w"],
      2 => ["pipe", "w"],
    ];
    $this->closePipes();
    $this->pipes = [];
    $process = proc_open(
      $this->getCmd(),
      $descriptorSpec,
      $this->pipes,
      $this->getCwd(),
      $this->getEnv()
    );

    if (is_resource($process)) {
      $this->process = $process;
      return $this->process;
    }

    $this->closePipes();
    throw new StaticSuiteException(sprintf('Cannot open "%s"', $this->getCmd()));
  }

  /**
   * Close all open pipes, if any.
   */
  protected function closePipes(): void {
    if (isset($this->pipes[0]) && is_resource($this->pipes[0])) {
      fclose($this->pipes[0]);
    }

    if (isset($this->pipes[1]) && is_resource($this->pipes[1])) {
      fclose($this->pipes[1]);
    }

    if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
      fclose($this->pipes[2]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStdIn() {
    return $this->pipes[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getStdOut() {
    return $this->pipes[1];
  }

  /**
   * {@inheritdoc}
   */
  public function readStdOut(): bool | string {
    return fgets($this->pipes[1]);
  }

  /**
   * {@inheritdoc}
   */
  public function getStdErr() {
    return $this->pipes[2];
  }

  /**
   * {@inheritdoc}
   */
  public function readStdErr(): bool | string {
    return fgets($this->pipes[2]);
  }

  /**
   * {@inheritdoc}
   */
  public function close(): int {
    $this->closePipes();
    return proc_close($this->process);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(string $stdIn = NULL): CliCommandResultInterface {
    $process = $this->open();
    if (is_resource($process)) {
      if ($stdIn !== NULL) {
        fwrite($this->pipes[0], $stdIn);
      }
      $stdOutContents = stream_get_contents($this->pipes[1]);
      $stdErrContents = stream_get_contents($this->pipes[2]);

      $this->closePipes();

      $returnCode = $this->close();

      return $this->cliCommandResultFactory->create($returnCode, $stdOutContents, $stdErrContents);
    }

    $this->closePipes();
    throw new StaticSuiteException(sprintf('Cannot execute "%s"', $this->getCmd()));
  }

}
