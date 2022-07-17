<?php

namespace Drupal\static_suite\Cli\Result;

/**
 * A factory that creates CliCommandResultInterface objects.
 */
class CliCommandResultFactory implements CliCommandResultFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function create(int $returnCode, string $stdOut = NULL, string $stdErr = NULL): CliCommandResultInterface {
    return new CliCommandResult($returnCode, $stdOut, $stdErr);
  }

}
