<?php

namespace Drupal\workspaces\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\workspaces\WorkspaceInterface;

/**
 * Defines the workspace switch event.
 */
class WorkspaceSwitchEvent extends Event {

  public function __construct(
    protected readonly ?WorkspaceInterface $workspace = NULL,
    protected readonly ?WorkspaceInterface $previousWorkspace = NULL,
  ) {}

  /**
   * Gets the new activate workspace.
   *
   * @return WorkspaceInterface|null
   *   A workspace entity, or NULL if we switched into Live.
   */
  public function getWorkspace(): ?WorkspaceInterface {
    return $this->workspace;
  }

  /**
   * Gets the previous active workspace.
   *
   * @return WorkspaceInterface|null
   *   A workspace entity, or NULL if we switched from Live.
   */
  public function getPreviousWorkspace(): ?WorkspaceInterface {
    return $this->previousWorkspace;
  }

}
