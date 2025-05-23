<?php

namespace Drupal\user\Plugin\Action;

use Drupal\Core\Action\ActionResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Removes a role from a user.
 */
#[Action(
  id: 'user_remove_role_action',
  label: new TranslatableMarkup('Remove a role from the selected users'),
  context_definitions: [
    'user' => new EntityContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The user you want to remove a role from."),
    ),
    'rid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("User role"),
      description: new TranslatableMarkup("The id of the role to remove"),
    ),
  ],
)]
class RemoveRoleUser extends ChangeUserRoleBase {


  /**
   * {@inheritdoc}
   */
  protected function doExecute(UserInterface $user, string $rid): ActionResult {
    // Skip removing the role from the user if they already don't have it.
    if ($user->hasRole($rid)) {
      // For efficiency manually save the original account before applying
      // any changes.
      $user->setOriginal(clone $user);
      $user->removeRole($rid)->save();

      return ActionResult::success($this->t('The @role has been removed from the user.', ['@role' =>$rid]));
    }
    return ActionResult::failure($this->t('The user does not have the role @role.', ['@role' =>$rid]));
  }

}
