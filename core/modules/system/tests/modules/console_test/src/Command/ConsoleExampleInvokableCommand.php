<?php

declare(strict_types=1);

namespace Drupal\console_test\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An example invokable command that declares help and usages on its attribute.
 *
 * @internal
 */
#[AsCommand(
  name: 'example:invokable',
  description: 'An example invokable command.',
  help: 'Example invokable command help text.',
  usages: ['example:invokable foo', 'example:invokable --bar'],
)]
class ConsoleExampleInvokableCommand {

  public function __invoke(OutputInterface $output): int {
    $output->writeln('Done with invokable command.');
    return Command::SUCCESS;
  }

}
