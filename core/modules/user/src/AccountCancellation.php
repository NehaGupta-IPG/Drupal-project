<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * User account cancellation service.
 */
class AccountCancellation {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * Cancels a user account.
   *
   * Since the user cancellation process needs to be run in a batch, either
   * Form API will invoke it, or batch_process() needs to be invoked after
   * calling this function and should define the path to redirect to.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account to cancel.
   * @param string $method
   *   The account cancellation method to use.
   * @param array $context
   *   (optional) Associative array with context value. For instance, when used
   *   in the form API, the submitted form values can be passed in this array.
   *
   * @see _user_cancel()
   */
  public function cancel(UserInterface $account, string $method, array $context = []): void {
    // Initialize batch (to set title).
    $batchBuilder = new BatchBuilder()->setTitle($this->t('Cancelling account'));
    batch_set($batchBuilder->toArray());

    // When the 'user_cancel_delete' method is used, user_delete() is called,
    // which invokes hook_ENTITY_TYPE_predelete() and hook_ENTITY_TYPE_delete()
    // for the user entity. Modules should use those hooks to respond to the
    // account deletion.
    if ($method !== 'user_cancel_delete') {
      // Allow modules to add further sets to this batch.
      $this->moduleHandler->invokeAll('user_cancel', [$context, $account, $method]);
    }

    // Finish the batch and actually cancel the account.
    $batchBuilder = new BatchBuilder()
      ->setTitle($this->t('Cancelling user account'))
      ->addOperation('_user_cancel', [$context, $account, $method]);

    // After cancelling the account, ensure that the user is logged out.
    if ($account->id() == $this->currentUser->id()) {
      // Batch API stores data in the session, so use the finished operation to
      // manipulate the current user's session id.
      $batchBuilder->setFinishCallback('_user_cancel_session_regenerate');
    }

    batch_set($batchBuilder->toArray());

    // Batch processing is either handled via Form API or has to be invoked
    // manually.
  }

}
