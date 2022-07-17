<?php

namespace Drupal\static_suite\Cli\Result;

/**
 * An interface to define the result of a CLI call.
 *
 * Provides access to stdout, stderr and the return code.
 */
interface CliCommandResultInterface {

  /**
   * Returns the contents of stdout.
   *
   * @return string|null
   */
  public function getStdOut(): ?string;

  /**
   * Returns the contents of stderr.
   *
   * @return string|null
   */
  public function getStdErr(): ?string;

  /**
   * Returns the return code.
   *
   * @return int
   */
  public function getReturnCode(): int;

}
