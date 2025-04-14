<?php

namespace Drupal\user\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ProtectedUserFieldConstraint constraint.
 */
class ProtectedUserFieldConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * User storage handler.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The current user.
   *
   * @var \Drupal\User\UserAuthInterface
   */
  protected $userAuth;

  /**
   * Constructs the object.
   *
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\User\UserAuthInterface $userAuth
   *   The user auth service.
   */
  public function __construct(UserStorageInterface $user_storage, AccountProxyInterface $current_user, UserAuthInterface $user_auth) {
    $this->userStorage = $user_storage;
    $this->currentUser = $current_user;
    $this->userAuth = $user_auth;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('current_user'),
      $container->get('user.auth')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    if (!isset($items)) {
      return;
    }
    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    $field = $items->getFieldDefinition();

    /** @var \Drupal\user\UserInterface $account */
    $account = $items->getEntity();
    if (!isset($account) || !empty($account->_skipProtectedUserFieldConstraint)) {
      return;
    }

    if (!$account->isNew() && $account->id() == $this->currentUser->id()) {
      /** @var \Drupal\user\UserInterface $account_unchanged */
      $account_unchanged = $this->userStorage
        ->loadUnchanged($account->id());

      $changed = FALSE;

      $value = $items->value;
      if ($field->getName() != 'pass' || !empty($value)) {
        $changed = $items->getValue() != $account_unchanged->get($field->getName())->getValue();
      }
      if ($changed && (!$this->userAuth->checkExistingPassword($account_unchanged))) {
        $this->context->addViolation($constraint->message, ['%name' => $field->getLabel()]);
      }
    }
  }

}
