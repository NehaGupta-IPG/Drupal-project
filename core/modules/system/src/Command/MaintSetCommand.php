<?php

declare(strict_types=1);

namespace Drupal\system\Command;

use Drupal\Core\State\StateInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\AskChoice;
use Symfony\Component\Console\Command\Command;

/**
 * Sets the maintenance mode value.
 *
 * @internal
 */
#[AsCommand(
  name: 'maint:set',
  description: 'Set maintenance mode.',
  help: 'Put site into maintenance mode with value 1, or remove site from maintenance mode with value 0.',
  usages: ['1', '0'],
  aliases: [
    'mset',
  ],
)]
class MaintSetCommand {

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Sets the maintenance mode value.
   */
  public function __invoke(
    #[Argument('The value to assign to maintenance mode (0 or 1).')]
    #[AskChoice('Enable maintenance mode?')]
    MaintenanceModeValue $value,
  ): int {
    $this->state->set('system.maintenance_mode', $value === MaintenanceModeValue::On);
    return Command::SUCCESS;
  }

}
