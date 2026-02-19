<?php

namespace Drupal\node\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to render a single node.
 */
class NodeViewController extends EntityViewController {

  /**
   * The current user.
   *
   * @var AccountInterface
   */
  protected $currentUser;

  /**
   * The entity repository service.
   *
   * @var EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Creates a NodeViewController object.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param RendererInterface $renderer
   *   The renderer service.
   * @param AccountInterface $current_user
   *   The current user.
   * @param EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, AccountInterface $current_user, EntityRepositoryInterface $entity_repository) {
    parent::__construct($entity_type_manager, $renderer);
    $this->currentUser = $current_user;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    return parent::view($node, $view_mode);
  }

  /**
   * The _title_callback for the page that renders a single node.
   *
   * @param EntityInterface $node
   *   The current node.
   *
   * @return string
   *   The page title.
   */
  public function title(EntityInterface $node) {
    return $this->entityRepository->getTranslationFromContext($node)->label();
  }

}
