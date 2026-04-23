<?php

namespace Drupal\content_moderation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\ParamConverter\DynamicEntityTypeParamConverterTrait;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\workspaces\Provider\WorkspaceProviderInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter decorator for activating Content Moderation workspaces.
 */
class ContentModerationEntityConverter implements ParamConverterInterface {

  use DynamicEntityTypeParamConverterTrait;

  public function __construct(
    #[AutowireDecorated]
    protected ParamConverterInterface $inner,
    protected LanguageManagerInterface $languageManager,
    #[Autowire(service: ContentModerationWorkspaceProvider::class)]
    protected WorkspaceProviderInterface $workspaceProvider,
    protected WorkspaceManagerInterface $workspaceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);

    if (!empty($definition['load_latest_revision'])) {
      $langcode = $this->languageManager->isMultilingual()
        ? $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId()
        : $this->languageManager->getDefaultLanguage()->getId();

      assert($this->workspaceProvider instanceof ContentModerationWorkspaceProvider);
      if ($workspace = $this->workspaceProvider->getOrCreateModerationWorkspace($entity_type_id, $value, $langcode)) {
        $this->workspaceManager->setActiveWorkspace($workspace, FALSE);
      }
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
