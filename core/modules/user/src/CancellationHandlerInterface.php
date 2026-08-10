<?php

declare(strict_types=1);

namespace Drupal\user;

/**
 * Handles user account cancellation for a content entity type.
 *
 * Content entity types opt in by registering a "user_cancel" entity handler.
 * They may use the default handler for standard ownership, revision author,
 * and publication fields, or provide a specialized implementation. Specialized
 * handlers may preserve entities that can be shared, even when content deletion
 * is requested.
 *
 * A content entity type registers a handler in its entity type definition:
 * @code
 * handlers: [
 *   'user_cancel' =>
 *     \Drupal\user\Entity\Handler\DefaultCancellationHandler::class,
 * ]
 * @endcode
 *
 * Handlers are invoked before hook_user_cancel() implementations and before
 * the account is blocked or deleted. They are invoked for both core and custom
 * cancellation methods; implementations must ignore unsupported methods.
 * Long-running work should be added to the account cancellation batch.
 *
 * @see \Drupal\user\Entity\Handler\DefaultCancellationHandler
 * @see user_cancel()
 * @see hook_user_cancel()
 * @see hook_user_cancel_methods_alter()
 */
interface CancellationHandlerInterface {

  /**
   * Disables the account and keeps its content.
   */
  public const METHOD_BLOCK = 'user_cancel_block';

  /**
   * Disables the account and unpublishes its content.
   */
  public const METHOD_BLOCK_UNPUBLISH = 'user_cancel_block_unpublish';

  /**
   * Deletes the account and reassigns its content to anonymous.
   */
  public const METHOD_REASSIGN = 'user_cancel_reassign';

  /**
   * Deletes the account and requests deletion of its content.
   *
   * A specialized handler may preserve or reassign content that can be shared.
   */
  public const METHOD_DELETE = 'user_cancel_delete';

  /**
   * Gets the handler priority.
   *
   * Handlers with a higher priority run before handlers with a lower priority.
   * Handlers with the same priority are ordered by entity type ID.
   *
   * @return int
   *   The handler priority.
   */
  public function getPriority(): int;

  /**
   * Applies a cancellation method to entities associated with an account.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account being cancelled.
   * @param string $method
   *   A core cancellation method constant from this interface or a custom
   *   method provided by hook_user_cancel_methods_alter().
   */
  public function cancelAccount(UserInterface $account, string $method): void;

}
