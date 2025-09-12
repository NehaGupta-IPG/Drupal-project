<?php

declare(strict_types=1);

namespace Drupal\package_manager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use PhpTuf\ComposerStager\API\Finder\Service\ExecutableFinderInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Factory\ProcessFactoryInterface;
use PhpTuf\ComposerStager\API\Process\Service\ComposerProcessRunnerInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Runs Composer through the current PHP interpreter.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class ComposerRunner implements ComposerProcessRunnerInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(
    private readonly ExecutableFinderInterface $executableFinder,
    private readonly ProcessFactoryInterface $processFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function run(array $command, ?PathInterface $cwd = NULL, array $env = [], ?OutputCallbackInterface $callback = NULL, int $timeout = ProcessInterface::DEFAULT_TIMEOUT): void {
    // Run Composer through the PHP interpreter so we don't have to rely on
    // PHP being in the PATH.
    array_unshift($command, (new PhpExecutableFinder())->find(), $this->executableFinder->find('composer'));

    $home = $this->fileSystem->getTempDirectory();
    $home .= '/package_manager_composer_home-';
    $home .= $this->configFactory->get('system.site')->get('uuid');
    $this->fileSystem->prepareDirectory($home, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

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
      $this->logger?->warning("Could not adjust the database server's idle timeout before running Composer: @message", [
        '@message' => $e->getMessage(),
      ]);
    }
    $process = $this->processFactory->create($command, $cwd, $env + ['COMPOSER_HOME' => $home]);
    $process->setTimeout($timeout);
    $process->mustRun($callback);
  }

}
