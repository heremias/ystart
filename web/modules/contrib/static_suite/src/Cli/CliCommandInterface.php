<?php

namespace Drupal\static_suite\Cli;

use Drupal\static_suite\Cli\Result\CliCommandResultInterface;

/**
 * An interface for CLI commands.
 */
interface CliCommandInterface {

  /**
   * Returns the CLI command.
   *
   * @return string
   *   The CLI command.
   */
  public function getCmd(): string;

  /**
   * Returns the working directory for the command.
   *
   * @return string|null
   *   The working directory for the command.
   */
  public function getCwd(): ?string;

  /**
   * Returns the environment variables for the command.
   *
   * @return array|null
   *   The environment variables for the command.
   */
  public function getEnv(): ?array;

  /**
   * Open a new CLI process.
   *
   * @return resource
   *   A resource representing the process, which should be closed using close()
   *   when you are finished with it. On failure, it throws an exception.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function open();

  /**
   * Get stdin from a running command.
   *
   * @return resource
   *   A stream resource for stdin.
   */
  public function getStdIn();

  /**
   * Get stdout from the running command.
   *
   * @return resource
   *   A stream resource for stdout.
   */
  public function getStdOut();

  /**
   * Read stdout from the running command.
   *
   * @return string|false
   *   A string or false if end of stream reached.
   */
  public function readStdOut(): bool | string;

  /**
   * Get stderr from the running command.
   *
   * @return resource
   *   A stream resource for stderr.
   */
  public function getStdErr();

  /**
   * Read stderr from the running command.
   *
   * @return string|false
   *   A string or false if end of stream reached.
   */
  public function readStdErr(): bool | string;

  /**
   * Close a previously open CLI process.
   *
   * @return int
   *   The exit code (termination status) of the process that was run.
   */
  public function close(): int;

  /**
   * Executes the command.
   *
   * @param string|null $stdIn
   *   Content that will be piped to the command.
   *
   * @return \Drupal\static_suite\Cli\Result\CliCommandResultInterface
   *   An object instance of CliCommandResultInterface.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   *   If the command cannot be executed.
   */
  public function execute(string $stdIn = NULL): CliCommandResultInterface;

}
