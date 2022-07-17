<?php

namespace Drupal\static_suite\Plugin;

/**
 * Interface for plugins that execute any generic asynchronous task.
 *
 * A task is asynchronously executed if run from a web-server environment, to
 * avoid blocking the main thread. The entry point is the init() method, which
 * decides whether the task should be synchronous or not:
 *
 * 1) When running on a web-server, a process is forked and run on the
 * background, releasing the main thread, which exits without waiting for the
 * forked process to finish. To do so, the fork() method executes a shell
 * command, which is usually a drush command that executes the same init()
 * method of the beginning. Since that second execution of init() is now done
 * on a CLI, it doesn't fork again, so the run() method gets executed.
 *
 * 2) When running on a CLI, no process is forked, so the run() method is
 * executed.
 */
interface AsyncTaskPluginInterface {

  /**
   * Get the id of the task this plugin is running.
   *
   * All plugins execute a task that must be identified. It's id is usually
   * "{PREFIX}-{PLUGIN_ID}" (e.g.- build-gatsby, deploy-s3, etc)
   *
   * @return string
   *   The task id
   */
  public function getTaskId(): string;

  /**
   * Decides whether the task should be synchronous or not.
   *
   * If task is asynchronous, it calls fork() to create a new process, or calls
   * run() otherwise.
   */
  public function init(): void;

  /**
   * Fork the process and make it asynchronous.
   */
  public function fork(): void;

  /**
   * Run the real task, directly of after being forked.
   */
  public function run(): void;

  /**
   * Get the command to be executed on a shell, as a string.
   *
   * Special characters have to be properly escaped, and proper quoting has to
   * be applied.
   *
   * @returns string
   *   The command to be executed on a shell.
   */
  public function getCommand(): string;

  /**
   * Get the initial working dir for the command.
   *
   * This must be an absolute directory path, or null if you want to use the
   * default value (the working dir of the current PHP process)
   *
   * @return string|null
   *   The initial working dir for the command.
   */
  public function getCwd(): ?string;

  /**
   * Get an array with the environment variables for the command.
   *
   * Return null to use the same environment as the current PHP process.
   *
   * @return array|null
   *   An array with the environment variables for the command.
   */
  public function getEnv(): ?array;

  /**
   * Get an absolute path to a file used to log the forking process.
   *
   * This log is useful when forking a process could be a tricky process, due
   * to user permissions, environment variables, etc.
   *
   * @return string|null
   *   An absolute path to a file used to log the forking process.
   */
  public function getForkLogPath(): ?string;

  /**
   * Tell whether the task should be executed asynchronously or not.
   *
   * @return bool
   *   True if task must be run asynchronously.
   */
  public function isAsync(): bool;

}
