<?php

declare(strict_types=1);

namespace Drupal\system\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\Form\CronForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to display Cron status.
 */
#[Block(
  id: "system_cron_status_block",
  admin_label: new TranslatableMarkup("Cron status")
)]
class CronStatusBlock extends BlockBase implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

  /**
   * Constructs a new CronStatusBlock instance.
   *
   * @param array $configuration
   *   An array of configuration settings.
   * @param mixed $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];

    $build['cron_status'] = [
      '#lazy_builder' =>
        [self::class . '::lazyBuilder', []],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer site configuration');
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return [
      'lazyBuilder',
    ];
  }

  /**
   * #lazy_builder callback; builds the cron form block.
   *
   * @return array
   *   A render array with the cron form.
   */
  public static function lazyBuilder(): array {
    $build = \Drupal::service('form_builder')->getForm(CronForm::class);

    // Hide parts of the form out of the block scope.
    $build['cron']['#access'] = FALSE;
    $build['actions']['#access'] = FALSE;

    return $build;
  }

}
