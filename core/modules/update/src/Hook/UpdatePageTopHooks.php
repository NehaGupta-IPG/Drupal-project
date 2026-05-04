<?php

namespace Drupal\update\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\update\UpdateManagerInterface;

/**
 * Page top hook for the update module.
 */
class UpdatePageTopHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly MessengerInterface $messenger,
    protected readonly AdminContext $adminContext,
    protected readonly AccountProxyInterface $account,
    protected readonly RendererInterface $renderer,
  ) {}

  /**
   * Implements hook_page_top().
   */
  #[Hook('page_top')]
  public function pageTop(): void {
    if ($this->adminContext->isAdminRoute($this->routeMatch->getRouteObject()) && $this->account->hasPermission('view update notifications')) {
      $route_name = $this->routeMatch->getRouteName();
      switch ($route_name) {
        // These pages don't need additional nagging.
        case 'update.status':
        case 'update.settings':
        case 'system.status':
        case 'system.theme_install':
        case 'system.batch_page.html':
          return;

        // If we are on the appearance or modules list, display a detailed
        // report
        // of the update status.
        case 'system.themes_page':
        case 'system.modules_list':
          $verbose = TRUE;
          break;
      }
      $status = $this->moduleHandler->invoke('update', 'runtime_requirements');
      foreach (['core', 'contrib'] as $report_type) {
        $type = 'update_' . $report_type;
        // hook_requirements() supports render arrays therefore we need to
        // render them before using
        // \Drupal\Core\Messenger\MessengerInterface::addStatus().
        if (isset($status[$type]['description']) && is_array($status[$type]['description'])) {
          $status[$type]['description'] = $this->renderer->renderInIsolation($status[$type]['description']);
        }
        if (!empty($verbose)) {
          if (isset($status[$type]['severity'])) {
            if ($status[$type]['severity'] === RequirementSeverity::Error) {
              $this->messenger->addError($status[$type]['description']);
            }
            elseif ($status[$type]['severity'] === RequirementSeverity::Warning) {
              $this->messenger->addWarning($status[$type]['description']);
            }
          }
        }
        else {
          if (isset($status[$type]) && isset($status[$type]['reason']) && $status[$type]['reason'] === UpdateManagerInterface::NOT_SECURE) {
            $this->messenger->addError($status[$type]['description']);
          }
        }
      }
    }
  }

}
