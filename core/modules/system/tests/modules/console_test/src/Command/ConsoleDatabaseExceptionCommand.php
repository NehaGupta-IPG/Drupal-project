<?php

declare(strict_types=1);

namespace Drupal\console_test\Command;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\SqlExecutionInfo;
use Drupal\Core\Database\Statement\FetchAs;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command throwing a database exception that carries statement information.
 *
 * @internal
 */
#[AsCommand(
  name: 'example:database-exception',
  description: 'Throws a database exception carrying SQL execution information.',
)]
class ConsoleDatabaseExceptionCommand extends Command {

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $info = new SqlExecutionInfo(
      'SELECT "b".* FROM "bananas_are_awesome" "b" WHERE "palm_height" > :db_condition_placeholder_0',
      [':db_condition_placeholder_0' => 180],
      ['fetch' => FetchAs::Object, 'allow_delimiter_in_query' => FALSE],
    );
    throw new DatabaseExceptionWrapper(
      $info->appendToMessage("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'palm_height'"),
      0,
      NULL,
      $info,
    );
  }

}
