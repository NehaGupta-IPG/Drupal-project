<?php

namespace Drupal\user\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\ActionResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Unblocks a user.
 */
#[Action(
  id: 'user_unblock_user_action',
  label: new TranslatableMarkup('Unblock the selected users'),
  context_definitions: [
    'user' => new EntityContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The user you want to unblock."),
    ),
  ],
)]
class UnblockUser extends ActionBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ActionResult {
    // @var \Drupal\user\UserInterface $user
    [$user] = $values;
    // Skip unblocking user if they are already unblocked.
    if ($user->isBlocked()) {
      $user->activate();
      $user->save();
      return ActionResult::success($this->t('The user has been unblocked.'));
    }
    return ActionResult::failure($this->t('The user is already unblocked.'));
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getContextValue('user');
    /** @var \Drupal\user\UserInterface $object */
    $access = $user->status->access('edit', $account, TRUE)
      ->andIf($user->access('update', $account, TRUE));

    return $return_as_object ? $access : $access->isAllowed();
  }

}
