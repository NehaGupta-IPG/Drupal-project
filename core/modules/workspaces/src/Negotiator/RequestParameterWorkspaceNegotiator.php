<?php

namespace Drupal\workspaces\Negotiator;

use Drupal\Component\Utility\Crypt;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\HttpFoundation\Request;

/**
 * Negotiates workspace from POST request parameters for workspace-safe forms.
 *
 * This negotiator reads the 'active_workspace_id' hidden field that is added
 * to workspace-safe forms by FormOperations::formAlter(). It only applies to
 * POST requests and does not persist the workspace to the session.
 *
 * @see \Drupal\Core\Form\FormBuilder::doBuildForm()
 * @see \Drupal\workspaces\Hook\FormOperations::formAlter()
 */
class RequestParameterWorkspaceNegotiator implements WorkspaceNegotiatorInterface, WorkspaceIdNegotiatorInterface {

  public function __construct(
    #[AutowireServiceClosure('current_user')]
    protected \Closure $currentUser,
    #[AutowireServiceClosure('csrf_token')]
    protected \Closure $csrfToken,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Check for the required parameters early.
    $form_id = $request->request->get('form_id');
    $form_token = $request->request->get('form_token');
    $active_workspace = $request->request->get('active_workspace_id');
    if (!is_string($form_id) || !is_string($form_token) || !is_string($active_workspace)) {
      return FALSE;
    }

    // Only apply for POST requests from authenticated users.
    if (!$request->isMethod('POST') || !($this->currentUser)()->isAuthenticated()) {
      return FALSE;
    }

    // Validate the CSRF token against the form_id placeholder.
    $token_placeholder = 'form_token_placeholder_' . Crypt::hashBase64($form_id);
    return ($this->csrfToken)()->validate($form_token, $token_placeholder);
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspaceId(Request $request): ?string {
    $workspace_id = $request->request->get('active_workspace_id');
    return is_string($workspace_id) ? $workspace_id : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace): void {
    // Do not persist - this negotiator is request-scoped only.
  }

  /**
   * {@inheritdoc}
   */
  public function unsetActiveWorkspace(): void {
    // Do not persist - this negotiator is request-scoped only.
  }

}
