<?php

namespace Drupal\static_suite\Utility;

/**
 * Methods for measuring the performance of a process.
 */
trait BenchmarkTrait {

  /**
   * Benchmark start time.
   *
   * @var float
   */
  protected $timeStart;

  /**
   * Benchmark end time.
   *
   * @var float
   */
  protected $timeEnd;

  /**
   * Starts benchmark.
   */
  protected function startBenchmark(): void {
    $this->timeStart = microtime(TRUE);
    $this->timeEnd = NULL;
  }

  /**
   * Ends benchmark.
   */
  protected function endBenchmark(): void {
    $this->timeEnd = microtime(TRUE);
  }

  /**
   * Get elapsed time.
   *
   * @return float
   *   Elapsed time.
   */
  protected function getBenchmark(): float {
    $timeEnd = $this->timeEnd ?: microtime(TRUE);
    return number_format(round($timeEnd - $this->timeStart, 3), 3);
  }

}
