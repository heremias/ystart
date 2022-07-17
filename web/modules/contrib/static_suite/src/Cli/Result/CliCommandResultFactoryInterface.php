<?php

namespace Drupal\static_suite\Cli\Result;

/**
 * An interface for factories that create CliCommandResultInterface objects.
 */
interface CliCommandResultFactoryInterface {

  /**
   * Creates a CliCommandResultInterface object.
   *
   * @param int $returnCode
   *   The return code.
   * @param string|null $stdOut
   *   The stdout contents.
   * @param string|null $stdErr
   *   The stderr contents.
   *
   * @return CliCommandResultInterface
   *   A CLI command result.
   */
  public function create(int $returnCode, string $stdOut = NULL, string $stdErr = NULL): CliCommandResultInterface;

}
