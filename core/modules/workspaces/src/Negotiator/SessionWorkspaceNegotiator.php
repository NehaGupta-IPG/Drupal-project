<?php

namespace Drupal\workspaces\Negotiator;

use Drupal\Core\Session\AccountInterface;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the session workspace negotiator.
 */
class SessionWorkspaceNegotiator implements WorkspaceNegotiatorInterface, WorkspaceIdNegotiatorInterface {

  public function __construct(
    protected readonly AccountInterface $currentUser,
    protected readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // This negotiator only applies if the current user is authenticated.
    return $this->currentUser->isAuthenticated();
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspaceId(Request $request): ?string {
    return $this->requestStack->getSession()->get('active_workspace_id');
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {
    $this->requestStack->getSession()->set('active_workspace_id', $workspace->id());
  }

  /**
   * {@inheritdoc}
   */
  public function unsetActiveWorkspace() {
    $this->requestStack->getSession()->remove('active_workspace_id');
  }

}
