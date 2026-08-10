<?php

/**
 * @file
 * Hooks provided by the User module.
 */

use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Act on user account cancellations.
 *
 * This hook is invoked from user_cancel() after registered "user_cancel"
 * entity handlers and before a user account is canceled. Handlers run by
 * descending priority, with ties resolved by entity type ID. Content entity
 * types should register a handler instead of using this hook.
 *
 * See user_cancel_methods() for the default account cancellation methods.
 * Modules may add further methods via hook_user_cancel_methods_alter().
 *
 * This hook is NOT invoked for the 'user_cancel_delete' account cancellation
 * method, although registered entity handlers are. Other reactions to account
 * deletion should use hook_ENTITY_TYPE_predelete() or hook_ENTITY_TYPE_delete()
 * for user entities.
 *
 * Expensive operations should be added to the global account cancellation batch
 * by using batch_set().
 *
 * @param array $edit
 *   The array of form values submitted by the user.
 * @param \Drupal\user\UserInterface $account
 *   The user object on which the operation is being performed.
 * @param string $method
 *   The account cancellation method.
 *
 * @see user_cancel_methods()
 * @see \Drupal\user\CancellationHandlerInterface
 * @see hook_user_cancel_methods_alter()
 */
function hook_user_cancel($edit, UserInterface $account, $method): void {
  \Drupal::logger('my_module')->notice(
    'Cancellation method %method was requested for account %id.',
    ['%method' => $method, '%id' => $account->id()],
  );
}

/**
 * Modify account cancellation methods.
 *
 * By implementing this hook, modules are able to add, customize, or remove
 * account cancellation methods. All defined methods are turned into radio
 * button form elements by user_cancel_methods() after this hook is invoked.
 * The following properties can be defined for each method:
 * - title: The radio button's title.
 * - description: (optional) A description to display on the confirmation form
 *   if the user is not allowed to select the account cancellation method. The
 *   description is NOT used for the radio button, but instead should provide
 *   additional explanation to the user seeking to cancel their account.
 * - access: (optional) A boolean value indicating whether the user can access
 *   a method. If 'access' is defined, the method cannot be configured as
 *   default method.
 *
 * @param array $methods
 *   An array containing user account cancellation methods, keyed by method id.
 *
 * @see user_cancel_methods()
 * @see \Drupal\user\Form\UserCancelForm
 */
function hook_user_cancel_methods_alter(&$methods) {
  $account = \Drupal::currentUser();
  // Limit access to disable account and unpublish content method.
  $methods['user_cancel_block_unpublish']['access'] = $account->hasPermission('administer site configuration');

  // Remove the content re-assigning method.
  unset($methods['user_cancel_reassign']);

  // Add a custom zero-out method.
  $methods['my_module_zero_out'] = [
    'title' => t('Delete the account and remove all content.'),
    'description' => t('All your content will be replaced by empty strings.'),
    // Access should be used for administrative methods only.
    'access' => $account->hasPermission('access zero-out account cancellation method'),
  ];
}

/**
 * Alter the username that is displayed for a user.
 *
 * Called by $account->getDisplayName() to allow modules to alter the username
 * that is displayed. Can be used to ensure user privacy in situations where
 * $account->getDisplayName() is too revealing. This hook is invoked both for
 * user entities and the anonymous user session object.
 *
 * @param string|Drupal\Component\Render\MarkupInterface $name
 *   The username that is displayed for a user. If a hook implementation changes
 *   this to an object implementing MarkupInterface it is the responsibility of
 *   the implementation to ensure the user's name is escaped properly. String
 *   values will be autoescaped.
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The object on which the operation is being performed. This object may be a
 *   user entity. If the object is an implementation of UserInterface you can
 *   use instanceof operator before accessing user entity methods. For example:
 *   @code
 *   if ($account instanceof UserInterface) {
 *      // Access user entity methods.
 *   }
 *   @endcode
 *
 * @see \Drupal\Core\Session\AccountInterface::getDisplayName()
 * @see sanitization
 */
function hook_user_format_name_alter(&$name, AccountInterface $account) {
  // Display the user's uid instead of name.
  if ($account->id()) {
    $name = t('User @uid', ['@uid' => $account->id()]);
  }
}

/**
 * The user just logged in.
 *
 * @param \Drupal\user\UserInterface $account
 *   The user object on which the operation was just performed.
 */
function hook_user_login(UserInterface $account): void {
  $config = \Drupal::config('system.date');
  // If the user has a NULL time zone, notify them to set a time zone.
  if (!$account->getTimezone() && $config->get('timezone.user.configurable') && $config->get('timezone.user.warn')) {
    \Drupal::messenger()
      ->addStatus(t('Configure your <a href=":user-edit">account time zone setting</a>.', [
        ':user-edit' => $account->toUrl('edit-form', [
          'query' => \Drupal::destination()
            ->getAsArray(),
          'fragment' => 'edit-timezone',
        ])->toString(),
      ]));
  }
}

/**
 * The user just logged out.
 *
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The user object on which the operation was just performed.
 */
function hook_user_logout(AccountInterface $account): void {
  \Drupal::database()->insert('logouts')
    ->fields([
      'uid' => $account->id(),
      'time' => time(),
    ])
    ->execute();
}

/**
 * @} End of "addtogroup hooks".
 */
