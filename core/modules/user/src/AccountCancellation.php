<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
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
    protected AccountCancelMethodManager $accountCancelMethodManager,
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
   */
  public function cancel(UserInterface $account, string $method, array $context = []): void {
    // Initialize batch (to set the title).
    $batchBuilder = new BatchBuilder()->setTitle($this->t('Cancelling account'));
    batch_set($batchBuilder->toArray());

    // Finish the batch and actually cancel the account.
    $batchBuilder = new BatchBuilder()
      ->setTitle($this->t('Cancelling user account'))
      ->addOperation(static::class . ':cancelAccount', [$account, $method, $context]);

    // After cancelling the account, ensure that the user is logged out.
    if ($account->id() == $this->currentUser->id()) {
      // Batch API stores data in the session, so use the finished operation to
      // manipulate the current user's session ID.
      $batchBuilder->setFinishCallback(static::class . ':regenerateSession');
    }

    batch_set($batchBuilder->toArray());

    // Batch processing is either handled via Form API or has to be invoked
    // manually.
  }

  /**
   * Provides a finish callback for the batch that actually cancels the account.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account to cancel.
   * @param string $method
   *   The account cancellation method to use.
   * @param array $options
   *   (optional) An associative array of options to control the behavior of
   *   the cancellation method. E.g., set `user_cancel_notify` to `TRUE` if
   *   a user should be notified when the account was blocked.
   */
  public function cancelAccount(UserInterface $account, string $method, array $options): void {
    if ($this->accountCancelMethodManager->hasDefinition($method)) {
      $this->accountCancelMethodManager->createInstance($method)->cancel($account, $options);
    }
    else {
      @trigger_error("Defining account cancellation methods by implementing hook_user_cancel_methods_alter() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. To define a new account cancellation method, create a plugin implementing \Drupal\user\AccountCancelMethodInterface. See https://www.drupal.org/node/3580745", E_USER_DEPRECATED);
      // In the legacy system, any custom method did execute the same code as
      // the 'block' core method. This was a bug. For backwards compatibility
      // reasons, we replicate the legacy behavior here. This will be removed
      // when the backwards compatibility layer is discontinued.
      // @see https://www.drupal.org/project/drupal/issues/3135592
      $this->accountCancelMethodManager->createInstance('user_cancel_block')->cancel($account, $options);
    }

    // After cancelling the account, ensure that the user is logged out. We
    // can't destroy their session, though, as we might have information in it,
    // and we can't regenerate it because batch API uses the session ID, we will
    // regenerate it in _user_cancel_session_regenerate().
    if ($account->id() == \Drupal::currentUser()->id()) {
      \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    }
  }

  /**
   * Finishes the batch processing for cancelling a user account.
   */
  public function regenerateSession(): void {
    // Regenerate the user's session instead of calling session_destroy() as we
    // want to preserve any messages that might have been set.
    \Drupal::request()->getSession()->migrate();
  }

  /**
   * Builds a form element for selecting the account cancellation method.
   *
   * @return array{"#options": array<string, string|\Stringable>, "#default_value": string, ...<string, array{"#confirm_description": string|\Stringable, "#access"?: bool}>}
   *   Form element for selecting the account cancellation method.
   */
  public function getMethodsFormElement(): array {
    $methods = [];
    foreach ($this->accountCancelMethodManager->getDefinitions() as $pluginId => $definition) {
      $plugin = $this->accountCancelMethodManager->createInstance($pluginId);

      $methods[$pluginId] = [
        'title' => $plugin->getPluginDefinition()['label'],
        'description' => $plugin->getPluginDefinition()['description'] ?? NULL,
        'access' => $plugin->access($this->currentUser),
      ];
    }

    $this->moduleHandler->alterDeprecated(
      'Implementing hook_user_cancel_methods_alter() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. To define a new account cancellation method, create a plugin implementing \Drupal\user\AccountCancelMethodInterface. To alter existing cancellation method plugins, implement hook_user_cancel_method_info_alter(). See https://www.drupal.org/node/3580745',
      'user_cancel_methods',
      $methods,
    );

    $element = [
      '#options' => [],
      '#default_value' => $this->configFactory->get('user.settings')->get('cancel_method'),
    ];

    foreach ($methods as $name => $method) {
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
