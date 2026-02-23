<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\Action;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Send welcome message to selected users action.
 */
#[Action(
  id: 'user_welcome_message_action',
  label: new TranslatableMarkup('Send welcome message to selected users'),
  category: new TranslatableMarkup('Custom'),
  type: 'user',
)]
final class SendWelcomeMessage extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    /** @var \Drupal\user\UserInterface $object */
    $access = $object->status->access('edit', $account, TRUE)
      ->andIf($object->access('update', $account, TRUE));

    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?AccountInterface $account = NULL): void {

    if (empty($account) || empty($account->getEmail())) {
      return;
    }

    $op = 'register_pending_approval';
    if ($account->isActive()) {
      // Determine the user approval method.
      switch ($this->configFactory->get('user.settings')->get('register')) {
        case UserInterface::REGISTER_ADMINISTRATORS_ONLY:
          $op = 'register_admin_created';
          break;

        case UserInterface::REGISTER_VISITORS:
        default:
          $op = 'register_no_approval_required';
      }
    }

    _user_mail_notify($op, $account);
  }

}
