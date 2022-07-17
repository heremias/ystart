<?php

namespace Drupal\static_suite\Cli\Result;

/**
 * The result of a CLI call.
 *
 * Provides access to stdout, stderr and the return code.
 */
class CliCommandResult implements CliCommandResultInterface {

  /**
   * The return code.
   *
   * @var int
   */
  protected $returnCode;

  /**
   * The stdout contents.
   *
   * @var string|null
   */
  protected $stdOut;

  /**
   * The stderr contents.
   *
   * @var string|null
   */
  protected $stdErr;

  /**
   * Creates a new result for a CLI command.
   *
   * @param int $returnCode
   *   The return code.
   * @param string|null $stdOut
   *   The stdout contents.
   * @param string|null $stdErr
   *   The stderr contents.
   */
  public function __construct(int $returnCode, string $stdOut = NULL, string $stdErr = NULL) {
    $this->returnCode = $returnCode;
    $this->stdOut = $stdOut;
    $this->stdErr = $stdErr;
  }

  /**
   * Returns the return code.
   *
   * @return int
   */
  public function getReturnCode(): int {
    return $this->returnCode;
  }

  /**
   * Returns the contents of stdout.
   *
   * @return string|null
   */
  public function getStdOut(): ?string {
    return $this->stdOut;
  }

  /**
   * Returns the contents of stderr.
   *
   * @return string|null
   */
  public function getStdErr(): ?string {
    return $this->stdErr;
  }

}
