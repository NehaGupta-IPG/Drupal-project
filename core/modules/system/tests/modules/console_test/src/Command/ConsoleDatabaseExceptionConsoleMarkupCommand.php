<?php

declare(strict_types=1);

namespace Drupal\console_test\Command;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\SqlExecutionInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command throwing a database exception with console markup in the values.
 *
 * Placeholder values are user input, so they can contain anything the console
 * output formatter would act on: a registered tag such as <error>, or a style
 * with a color that does not exist.
 *
 * @internal
 */
#[AsCommand(
  name: 'example:database-exception-console-markup',
  description: 'Throws a database exception whose values look like console markup.',
)]
class ConsoleDatabaseExceptionConsoleMarkupCommand extends Command {

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $markup = '<error>eaten?</error> <fg=nosuchcolor>invalid</>';
    throw new DatabaseExceptionWrapper(
      "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '$markup' for key 'PRIMARY'",
      0,
      NULL,
      new SqlExecutionInfo(
        'INSERT INTO "key_value" ("name") VALUES (:db_insert_placeholder_0)',
        [':db_insert_placeholder_0' => $markup],
      ),
    );
  }

}
