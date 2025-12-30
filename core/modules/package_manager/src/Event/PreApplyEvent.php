<?php

declare(strict_types=1);

namespace Drupal\package_manager\Event;

use Drupal\package_manager\ImmutablePathList;
use Drupal\package_manager\SandboxManagerBase;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;

/**
 * Event fired before staged changes are synced to the active directory.
 */
final class PreApplyEvent extends SandboxValidationEvent {

  /**
   * The list of paths to ignore in the active and stage directories.
   *
   * @var \Drupal\package_manager\ImmutablePathList
   */
  public readonly ImmutablePathList $excludedPaths;

  public function __construct(SandboxManagerBase $sandboxManager, PathListInterface $excluded_paths) {
    parent::__construct($sandboxManager);
    $this->excludedPaths = new ImmutablePathList($excluded_paths);
  }

}
