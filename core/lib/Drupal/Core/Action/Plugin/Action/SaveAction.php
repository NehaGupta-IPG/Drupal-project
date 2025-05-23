<?php

namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Action\ActionResult;
use Drupal\Core\Action\Plugin\Action\Derivative\EntitySaveActionDeriver;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action that can save any entity.
 */
#[Action(
  id: 'entity:save_action',
  action_label: new TranslatableMarkup('Save'),
  deriver: EntitySaveActionDeriver::class
)]
class SaveAction extends EntityActionBase {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a SaveAction object.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ActionResult {
    // @var \Drupal\Core\Entity\EntityInterface $entity
    [$entity] = $values;
    $entity_is_new = $entity->isNew();
    $entity->setChangedTime($this->time->getRequestTime())->save();
    $entity_link = $entity->toLink()->toString();
    // If the entity is new, we need to return it in the result.
    if ($entity_is_new) {
      return ActionResult::success(
        $this->t('The @type %label has been created.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity_link]),
        [$entity->getEntityTypeId() => $entity]
      );
    }
    return ActionResult::success($this->t('The @type %label has been updated.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity_link]));
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $entity = $this->getContextValue($this->getDerivativeId());
    if($entity->isNew()) {
      return $entity->access('create', $account, $return_as_object);
    }
    // It's not necessary to check the changed field access here, because
    // Drupal\Core\Field\ChangedFieldItemList would anyway return 'not allowed'.
    // Also changing the changed field value is only a workaround to trigger an
    // entity resave. Without a field change, this would not be possible.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    return $entity->access('update', $account, $return_as_object);
  }

}
