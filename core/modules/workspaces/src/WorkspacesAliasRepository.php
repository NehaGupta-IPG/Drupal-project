<?php

namespace Drupal\workspaces;

use Drupal\path_alias\AliasRepository;

/**
 * Provides workspace-specific path alias lookup queries.
 */
class WorkspacesAliasRepository extends AliasRepository {

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Sets the workspace manager.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   *
   * @return $this
   */
  public function setWorkspacesManager(WorkspaceManagerInterface $workspace_manager) {
    $this->workspaceManager = $workspace_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseQuery() {
    // Don't alter any queries if we're not in a workspace context.
    if (!$this->workspaceManager->hasActiveWorkspace()) {
      return parent::getBaseQuery();
    }

    $active_workspace = $this->workspaceManager->getActiveWorkspace();

    $query = $this->connection->select('path_alias', 'original_base_table');
    if ($this->connection->driver() == 'mongodb') {
      $query->leftJoin('workspace_association', 'wa',
        $query->joinCondition()
          ->condition("%alias.target_entity_type_id", 'path_alias')
          ->compare("%alias.target_entity_id", "original_base_table.id")
          ->condition("%alias.workspace", $active_workspace->id())
      );

      $coalesce_field = [
        '$ifNull' => [
          '$' . $this->connection->escapeField('wa.target_entity_revision_id'),
          '$' . $this->connection->escapeField('original_base_table.revision_id'),
        ],
      ];

      $query->innerJoin('path_alias', 'base_table',
        $query->joinCondition()
          ->compare("base_table.path_alias_current_revision.revision_id", serialize($coalesce_field))
          ->condition('base_table.path_alias_current_revision.status', TRUE),
      );
    }
    else {
      $wa_join = $query->leftJoin('workspace_association', NULL, "[%alias].[target_entity_type_id] = 'path_alias' AND [%alias].[target_entity_id] = [original_base_table].[id] AND [%alias].[workspace] = :active_workspace_id", [
        ':active_workspace_id' => $active_workspace->id(),
      ]);
      $query->innerJoin('path_alias_revision', 'base_table', "[%alias].[revision_id] = COALESCE([$wa_join].[target_entity_revision_id], [original_base_table].[revision_id])");
      $query->condition('base_table.status', 1);
    }

    return $query;
  }

}
