<?php

declare(strict_types=1);

namespace Drupal\console_test\Command;

use Drupal\Core\Database\SchemaObjectExistsException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command throwing a database exception that carries no statement information.
 *
 * Database exceptions thrown by Drupal itself, rather than by a database
 * driver, carry no SQL execution information.
 *
 * @internal
 */
#[AsCommand(
  name: 'example:database-exception-no-info',
  description: 'Throws a database exception carrying no SQL execution information.',
)]
class ConsoleDatabaseExceptionNoInfoCommand extends Command {

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    throw new SchemaObjectExistsException("Table 'bananas_are_awesome' already exists.");
  }

}
