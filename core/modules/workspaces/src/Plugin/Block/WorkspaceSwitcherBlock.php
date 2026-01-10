<?php

namespace Drupal\workspaces\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\workspaces\Form\WorkspaceSwitcherForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Workspace switcher' block.
 */
#[Block(
  id: "workspace_switcher",
  admin_label: new TranslatableMarkup("Workspace switcher"),
  category: new TranslatableMarkup("Workspace")
)]
class WorkspaceSwitcherBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      'form' => $this->formBuilder->getForm(WorkspaceSwitcherForm::class),
      '#cache' => [
        'contexts' => $this->entityTypeManager->getDefinition('workspace')->getListCacheContexts(),
        'tags' => $this->entityTypeManager->getDefinition('workspace')->getListCacheTags(),
      ],
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermissions($account, [
      'view own workspace',
      'view any workspace',
      'administer workspaces',
    ], 'OR');
  }

}
