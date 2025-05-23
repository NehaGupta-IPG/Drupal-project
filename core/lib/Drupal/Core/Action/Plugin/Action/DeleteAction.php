<?php

namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Core\Action\ActionResult;
use Drupal\Core\Action\Plugin\Action\Derivative\EntityDeleteActionDeriver;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
//use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Redirects to an entity deletion form.
 */
#[Action(
  id: 'entity:delete_action',
  action_label: new TranslatableMarkup('Delete'),
  deriver: EntityDeleteActionDeriver::class
)]
class DeleteAction extends EntityActionBase {
//
//  /**
//   * The tempstore object.
//   *
//   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
//   */
//  protected $tempStore;
//
//  /**
//   * The current user.
//   *
//   * @var \Drupal\Core\Session\AccountInterface
//   */
//  protected $currentUser;
//
//  /**
//   * Constructs a new DeleteAction object.
//   *
//   * @param array $configuration
//   *   A configuration array containing information about the plugin instance.
//   * @param string $plugin_id
//   *   The plugin ID for the plugin instance.
//   * @param mixed $plugin_definition
//   *   The plugin implementation definition.
//   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
//   *   The entity type manager.
//   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
//   *   The tempstore factory.
//   * @param \Drupal\Core\Session\AccountInterface $current_user
//   *   Current user.
//   */
//  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user) {
//    $this->currentUser = $current_user;
//    $this->tempStore = $temp_store_factory->get('entity_delete_multiple_confirm');
//
//    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
//    return new static(
//      $configuration,
//      $plugin_id,
//      $plugin_definition,
//      $container->get('entity_type.manager'),
//      $container->get('tempstore.private'),
//      $container->get('current_user')
//    );
//  }
  // @Todo Solve.
//  /**
//   * {@inheritdoc}
//   */
//  public function executeMultiple(array $entities) {
//    /** @var \Drupal\Core\Entity\EntityInterface[] $entities */
//    $selection = [];
//    foreach ($entities as $entity) {
//      $langcode = $entity->language()->getId();
//      $selection[$entity->id()][$langcode] = $langcode;
//    }
//    $this->tempStore->set($this->currentUser->id() . ':' . $this->getPluginDefinition()['type'], $selection);
//  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(EntityInterface $entity): ActionResult {
    // Get the entity title.
    $entity_label = $entity->label();
    $entity_type_label = $entity->getEntityType()->getLabel();
    $entity->delete();
    // @Todo Solve backwards compatibility with executeMultiple.
    //  $this->executeMultiple([$entity]);
    return ActionResult::success($this->t('The @type %label has been deleted.', ['@type' => $entity_type_label, '%label' => $entity_label]));
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $entity = $this->getContextValue($this->getDerivativeId());
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    return $entity->access('delete', $account, $return_as_object);
  }

}
