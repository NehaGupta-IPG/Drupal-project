<?php

declare(strict_types=1);

namespace Drupal\system\Command;

use Drupal\Core\State\StateInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints the maintenance mode value and fails if it is enabled.
 *
 * @internal
 */
#[AsCommand(
  name: 'maint:status',
  description: 'Print maintenance mode (1 or 0). Fails if enabled.',
  help: <<<'HELP'
    Prints 1 if maintenance mode is enabled, 0 if not. When enabled, this command fails with exit code 3. This special exit code distinguishes from a failure to complete.

    Only run cron when Drupal is not in maintenance mode:

      <info>dr maint:status && dr cron</info>

    Scripts that only want the value can append <info>|| true</info>:

      <info>mode=$(dr maint:status || true)</info>
    HELP,
  usages: [
    '&& dr cron',
    '|| true',
  ],
  aliases: [
    'mstatus',
  ],
)]
class MaintStatusCommand {

  /**
   * Exit code when maintenance mode is enabled.
   *
   * Distinct from Command::FAILURE so callers can tell "maintenance mode is
   * on" apart from "the command failed to run".
   */
  public const int EXIT_ERROR_WITH_CLARITY = 3;

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Prints the maintenance mode value and fails if it is enabled.
   */
  public function __invoke(OutputInterface $output): int {
    $enabled = (bool) $this->state->get('system.maintenance_mode');
    $output->writeln($enabled ? '1' : '0');
    return $enabled ? self::EXIT_ERROR_WITH_CLARITY : Command::SUCCESS;
  }

}
