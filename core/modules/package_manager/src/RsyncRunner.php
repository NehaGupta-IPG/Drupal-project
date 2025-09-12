<?php

declare(strict_types=1);

namespace Drupal\package_manager;

use Drupal\Core\Database\Connection;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Process\Service\RsyncProcessRunnerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Runs rsync.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class RsyncRunner implements RsyncProcessRunnerInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(
    private readonly RsyncProcessRunnerInterface $decorated,
    private readonly Connection $database,
  ) {}

  /**
   * [@inheritdoc
   */
  public function run(array $command, ?PathInterface $cwd = NULL, array $env = [], ?OutputCallbackInterface $callback = NULL, int $timeout = ProcessInterface::DEFAULT_TIMEOUT): void {
    try {
      $this->database->setIdleTimeout($timeout + 5);
    }
    catch (\BadMethodCallException) {
      // If the database driver doesn't support setting the idle timeout, this
      // exception will be thrown and there's nothing else we need to do.
    }
    catch (\Throwable $e) {
      // If we weren't able to set the idle timeout for whatever reason, bravely
      // continue anyway and hope for the best, but do log the problem for
      // forensic purposes.
      $this->logger?->warning("Could not adjust the database server's idle timeout before running rsync: @message", [
        '@message' => $e->getMessage(),
      ]);
    }
    $this->decorated->run($command, $cwd, $env, $callback, $timeout);
  }

}
