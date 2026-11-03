<?php

declare(strict_types=1);

namespace DrCommandPackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A console command contributed by a non-module Composer package.
 *
 * @see \Drupal\KernelTests\Core\DrupalKernel\PhpPackageExtensionsDetailsTest
 */
#[AsCommand(
  name: 'php-command:test',
  description: 'A command provided by a Composer package that is not a Drupal module.',
)]
class PhpPackageTestCommand {

  /**
   * {@inheritdoc}
   */
  public function __invoke(SymfonyStyle $io): int {
    $io->success('PHP dr tested.');
    return Command::SUCCESS;
  }

}
