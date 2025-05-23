<?php

namespace Drupal\user\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Action\ActionResult;
use Drupal\user\UserInterface;

/**
 * Adds a role to a user.
 */
#[Action(
  id: 'user_add_role_action',
  label: new TranslatableMarkup('Add a role to the selected user'),
  context_definitions: [
    'user' => new EntityContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The user you want to add a role to."),
    ),
    'rid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("User role"),
    ),
  ],
)]
class AddRoleUser extends ChangeUserRoleBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(UserInterface $user, string $rid): ActionResult {
    // Skip adding the role to the user if they already have it.
    if (!$user->hasRole($rid)) {
      // For efficiency manually save the original account before applying
      // any changes.
      $user->setOriginal(clone $user);
      $user->addRole($rid)->save();
      return ActionResult::success($this->t('The @role has been added to the user.', ['@role' =>$rid]));
    }
    return ActionResult::failure($this->t('The user already has the role @role.', ['@role' =>$rid]));
  }

}
