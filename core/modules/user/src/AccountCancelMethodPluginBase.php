<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Base class for account cancellation methods plugins.
 */
abstract class AccountCancelMethodPluginBase extends PluginBase implements AccountCancelMethodInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    #[Autowire(service: 'logger.channel.user')]
    protected LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    return TRUE;
  }

}
