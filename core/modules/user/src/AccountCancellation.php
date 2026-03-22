<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
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
    protected ConfigFactoryInterface $configFactory,
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
    // Initialize batch (to set the title).
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

  /**
   * Returns the available account cancellation methods.
   *
   * @return array<string, array{title: string|\Stringable, description: string|\Stringable, access?: bool}>
   *   An array containing all account cancellation methods.
   *
   * @see hook_user_cancel_methods_alter()
   */
  public function getMethods(): array {
    $anonymousName = $this->configFactory->get('user.settings')->get('anonymous');

    // Built-in user account cancellation methods.
    $methods = [
      'user_cancel_block' => [
        'title' => $this->t('Disable the account and keep its content.'),
        'description' => $this->t('Your account will be blocked and you will no longer be able to log in. All of your content will remain attributed to your username.'),
      ],
      'user_cancel_block_unpublish' => [
        'title' => $this->t('Disable the account and unpublish its content.'),
        'description' => $this->t('Your account will be blocked and you will no longer be able to log in. All of your content will be hidden from everyone but administrators.'),
      ],
      'user_cancel_reassign' => [
        'title' => $this->t('Delete the account and make its content belong to the %anonymous-name user. This action cannot be undone.', [
          '%anonymous-name' => $anonymousName,
        ]),
        'description' => $this->t('Your account will be removed and all account information deleted. All of your content will be assigned to the %anonymous-name user.', [
          '%anonymous-name' => $anonymousName,
        ]),
      ],
      'user_cancel_delete' => [
        'title' => $this->t('Delete the account and its content. This action cannot be undone.'),
        'description' => $this->t('Your account will be removed and all account information deleted. All of your content will also be deleted.'),
        'access' => $this->currentUser->hasPermission('administer users'),
      ],
    ];

    // Allow modules to customize account cancellation methods.
    $this->moduleHandler->alter('user_cancel_methods', $methods);

    return $methods;
  }

  /**
   * Builds a form element for selecting the account cancellation method.
   *
   * @return array{"#options": array<string, string|\Stringable>, "#default_value": string, ...<string, array{"#confirm_description": string|\Stringable, "#access"?: bool}>}
   *   Form element for selecting the account cancellation method.
   */
  public function getMethodsFormElement(): array {
    $element = [
      '#options' => [],
      '#default_value' => $this->configFactory->get('user.settings')->get('cancel_method'),
    ];

    foreach ($this->getMethods() as $name => $method) {
      $element['#options'][$name] = $method['title'];
      // Add the description for the confirmation form. This description is
      // never shown for the cancel method option, only on the confirmation
      // form. Therefore, we use a custom #confirm_description property.
      if (isset($method['description'])) {
        $element[$name]['#confirm_description'] = $method['description'];
      }
      if (isset($method['access'])) {
        $element[$name]['#access'] = $method['access'];
      }
    }

    return $element;
  }

}
