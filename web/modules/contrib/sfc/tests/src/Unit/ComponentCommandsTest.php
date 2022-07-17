<?php

namespace Drupal\Tests\sfc\Unit;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\sfc\ComponentBase;
use Drush\SiteAlias\ProcessManager;
use Drupal\sfc\Commands\ComponentCommands;
use Drupal\sfc\ComponentPluginManager;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Process\Process;

/**
 * Tests methods provided by the component name helper.
 *
 * @coversDefaultClass \Drupal\sfc\Commands\ComponentCommands
 *
 * @group sfc
 */
class ComponentCommandsTest extends UnitTestCase {

  /**
   * Tests the ::write method.
   */
  public function testWrite() {
    $manager = $this->createMock(ComponentPluginManager::class);
    $component = $this->createMock(ComponentBase::class);
    $component->expects($this->once())
      ->method('writeAssets');
    $manager->expects($this->once())
      ->method('createInstance')
      ->withAnyParameters()
      ->willReturn($component);
    $commands = new ComponentCommands($manager);
    $commands->write('say_hello');
  }

  /**
   * Tests the ::watch method.
   */
  public function testWatch() {
    vfsStream::setup('sfc');
    $process_manager = $this->createMock(ProcessManager::class);
    $process = $this->createMock(Process::class);
    $process_manager->method('drush')->willReturn($process);
    $site_alias_manager = $this->createMock(SiteAliasManagerInterface::class);
    $alias = $this->createMock(SiteAlias::class);
    $site_alias_manager->method('getSelf')->willReturn($alias);
    // Empty definitions.
    $manager = $this->createMock(ComponentPluginManager::class);
    $manager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([]);
    $watch_file_path = 'vfs://sfc/sfc_watch_file.txt';
    $commands = new TestComponentCommands($manager, $watch_file_path);
    $commands->setProcessManager($process_manager);
    $commands->setSiteAliasManager($site_alias_manager);
    $commands->watch(['run-once' => TRUE]);
    $this->assertStringContainsString('No components found.', CustomOutput::$output);
    $this->assertFileDoesNotExist($watch_file_path);
    CustomOutput::$output = '';
    // Negative result.
    $component = $this->createMock(ComponentBase::class);
    $component->expects($this->once())
      ->method('shouldWriteAssets')
      ->willReturn(FALSE);
    $manager = $this->createMock(ComponentPluginManager::class);
    $manager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([1 => []]);
    $manager->expects($this->any())
      ->method('createInstance')
      ->willReturn($component);
    $commands = new TestComponentCommands($manager, $watch_file_path);
    $commands->setProcessManager($process_manager);
    $commands->setSiteAliasManager($site_alias_manager);
    $commands->watch(['run-once' => TRUE]);
    $this->assertStringNotContainsString('Writing change for 1', CustomOutput::$output);
    $this->assertFileDoesNotExist($watch_file_path);
    CustomOutput::$output = '';
    // Positive result.
    $component = $this->createMock(ComponentBase::class);
    $component->expects($this->once())
      ->method('shouldWriteAssets')
      ->willReturn(TRUE);
    $manager = $this->createMock(ComponentPluginManager::class);
    $manager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([1 => []]);
    $manager->expects($this->any())
      ->method('createInstance')
      ->willReturn($component);
    $commands = new TestComponentCommands($manager, $watch_file_path);
    $commands->setProcessManager($process_manager);
    $commands->setSiteAliasManager($site_alias_manager);
    $commands->watch(['run-once' => TRUE]);
    $this->assertStringContainsString('Writing assets for 1', CustomOutput::$output);
    $this->assertFileExists($watch_file_path);
    CustomOutput::$output = '';
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  protected function tearDown(): void {
    vfsStream::setup('sfc');
    if (is_file('vfs://sfc/sfc_watch_file.txt')) {
      unlink('vfs://sfc/sfc_watch_file.txt');
    }
  }

}

// phpcs:disable
// @codeCoverageIgnoreStart

class CustomOutput extends Output {

  public static $output = '';

  protected function doWrite($message, $newline) {
    static::$output .= $message;
  }

}

class TestComponentCommands extends ComponentCommands {

  protected function output() {
    return new CustomOutput();
  }

}

// phpcs:enable
// @codeCoverageIgnoreEnd
