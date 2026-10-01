<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Command;

use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Command\MaintSetCommand;
use Drupal\system\Command\MaintStatusCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the maint:set and maint:status commands.
 */
#[Group('Console')]
#[RunTestsInSeparateProcesses]
#[CoversClass(MaintSetCommand::class)]
#[CoversClass(MaintStatusCommand::class)]
class MaintCommandTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Tests setting and checking maintenance mode.
   */
  public function testMaint(): void {
    $state = $this->container->get('state');
    $set = new CommandTester(new MaintSetCommand($state));
    $status = new CommandTester(new MaintStatusCommand($state));

    $this->assertSame(Command::SUCCESS, $set->execute(['value' => '1']));
    $this->assertSame(MaintStatusCommand::EXIT_ERROR_WITH_CLARITY, $status->execute([]));
    $this->assertSame('1', trim($status->getDisplay()));

    $this->assertSame(Command::SUCCESS, $set->execute(['value' => '0']));
    $this->assertSame(Command::SUCCESS, $status->execute([]));
    $this->assertSame('0', trim($status->getDisplay()));

    // With no argument, the command prompts for a value.
    $set->setInputs(['1']);
    $this->assertSame(Command::SUCCESS, $set->execute([]));
    $this->assertStringContainsString('Enable maintenance mode?', $set->getDisplay());
    $this->assertSame(MaintStatusCommand::EXIT_ERROR_WITH_CLARITY, $status->execute([]));

    $this->expectException(InvalidArgumentException::class);
    $set->execute(['value' => 'maybe']);
  }

}
