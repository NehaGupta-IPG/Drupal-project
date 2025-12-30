<?php

namespace Drupal\user\Plugin\views\argument;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\ManyToOne;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allow role ID(s) as argument.
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
 id: 'user__roles_rid'
)]
class RolesRid extends ManyToOne {

  /**
   * The role entity storage.
   *
   * @var \Drupal\user\RoleStorage
   */
  protected $roleStorage;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->roleStorage = $entity_type_manager->getStorage('user_role');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function titleQuery() {
    $entities = $this->roleStorage->loadMultiple($this->value);
    $titles = [];
    foreach ($entities as $entity) {
      $titles[] = $entity->label();
    }
    return $titles;
  }

}
