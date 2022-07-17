<?php

namespace Drupal\static_suite\Cli;

/**
 * An interface for factories that create CliCommandInterface objects.
 */
interface CliCommandFactoryInterface {

  /**
   * Creates a CliCommandFactory object.
   *
   * @param string $cmd
   *   The CLI command to execute.
   * @param string|null $cwd
   *   The working directory in which the call will be executed.
   * @param array|null $env
   *   Environment variables - defaults to the current environment.
   *
   * @return CliCommandInterface
   *   A CLI command.
   */
  public function create(string $cmd, string $cwd = NULL, array $env = NULL): CliCommandInterface;

}
