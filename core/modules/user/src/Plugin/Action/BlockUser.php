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
 * Blocks a user.
 */
#[Action(
  id: 'user_block_user_action',
  label: new TranslatableMarkup('Block the selected user'),
  context_definitions: [
    'user' => new EntityContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The user to be blocked."),
    ),
  ],
)]
class BlockUser extends ActionBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(UserInterface $user): ActionResult {
    // Skip blocking user if they are already blocked.
    if ($user->isActive()) {
      // For efficiency manually save the original account before applying any
      // changes.
      $user->setOriginal(clone $user);
      $user->block();
      $user->save();
      return ActionResult::success($this->t('The user has been blocked.'));
    }
    return ActionResult::failure($this->t('The user is already blocked.'));
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
