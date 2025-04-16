<?php

declare(strict_types=1);

namespace Drupal\workspaces\Negotiator;

use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the static workspace negotiator.
 */
class StaticWorkspaceNegotiator implements WorkspaceNegotiatorInterface, WorkspaceIdNegotiatorInterface {

  /**
   * The active workspace.
   */
  protected ?WorkspaceInterface $workspace = NULL;

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request): bool {
    return isset($this->workspace);
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspaceId(Request $request): ?string {
    return $this->workspace?->id();
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace): void {
    // @todo Should this negotiator only work with managed workspaces?
    $this->workspace = $workspace;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetActiveWorkspace(): void {
    unset($this->workspace);
  }

}
