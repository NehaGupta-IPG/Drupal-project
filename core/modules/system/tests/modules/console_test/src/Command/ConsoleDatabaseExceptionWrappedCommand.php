<?php

declare(strict_types=1);

namespace Drupal\console_test\Command;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\SqlExecutionInfo;
use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command throwing a database exception wrapped by another exception.
 *
 * This is how most query failures surface: the layer that ran the query
 * rethrows one of its own exceptions, keeping the database exception as the
 * previous throwable.
 *
 * @internal
 */
#[AsCommand(
  name: 'example:database-exception-wrapped',
  description: 'Throws an exception wrapping a database exception.',
)]
class ConsoleDatabaseExceptionWrappedCommand extends Command {

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $info = new SqlExecutionInfo(
      'SELECT "b".* FROM "bananas_are_awesome" "b" WHERE "palm_height" > :db_condition_placeholder_0',
      [':db_condition_placeholder_0' => 180],
    );
    $exception = new DatabaseExceptionWrapper(
      $info->appendToMessage("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'palm_height'"),
      0,
      NULL,
      $info,
    );
    // Wrapped as \Drupal\Core\Entity\Sql\SqlContentEntityStorage does it.
    throw new EntityStorageException($exception->getMessage(), 0, $exception);
  }

}
