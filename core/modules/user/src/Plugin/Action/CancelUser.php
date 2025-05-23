<?php

namespace Drupal\user\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\ActionResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cancels a user account.
 */
#[Action(
  id: 'user_cancel_user_action',
  label: new TranslatableMarkup('Cancel the selected user accounts'),
  context_definitions: [
    'user' => new EntityContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The user you want to cancel."),
    ),
  ],
)]
class CancelUser extends ActionBase {
  // @Todo Solve.
//
//  /**
//   * Constructs a CancelUser object.
//   *
//   * @param array $configuration
//   *   A configuration array containing information about the plugin instance.
//   * @param string $plugin_id
//   *   The plugin ID for the plugin instance.
//   * @param mixed $plugin_definition
//   *   The plugin implementation definition.
//   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
//   *   The tempstore factory.
//   * @param \Drupal\Core\Session\AccountInterface $current_user
//   *   Current user.
//   */
//  public function __construct(
//    array $configuration,
//    $plugin_id,
//    $plugin_definition,
//    protected readonly PrivateTempStoreFactory $tempStoreFactory,
//    protected readonly AccountInterface $currentUser) {
//    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
//      $container->get('tempstore.private'),
//      $container->get('current_user')
//    );
//  }
  // @Todo Solve.
//
//  /**
//   * {@inheritdoc}
//   */
//  public function executeMultiple(array $entities) {
//    $this->tempStoreFactory->get('user_user_operations_cancel')->set($this->currentUser->id(), $entities);
//  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(UserInterface $user): ActionResult {
    $user->delete();
    return ActionResult::success($this->t('The user has been deleted.'));
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getContextValue('user');

    return $user->access('delete', $account, $return_as_object);
  }

}
