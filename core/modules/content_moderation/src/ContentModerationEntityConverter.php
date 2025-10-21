<?php

namespace Drupal\content_moderation;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\DynamicEntityTypeParamConverterTrait;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter decorator for activating Content Moderation workspaces.
 */
class ContentModerationEntityConverter implements ParamConverterInterface {

  use DynamicEntityTypeParamConverterTrait;

  public function __construct(
    protected ParamConverterInterface $inner,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected WorkspaceManagerInterface $workspaceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);

    $id = implode('.', ['cm', $entity_type_id, $value]);
    if (!empty($definition['load_latest_revision'])
      && ($workspace = $this->entityTypeManager->getStorage('workspace')->load($id))) {
      $this->workspaceManager->setActiveWorkspace($workspace, FALSE);
    }

    return $this->inner->convert($value, $definition, $name, $defaults);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return $this->inner->applies($definition, $name, $route);
  }

}
