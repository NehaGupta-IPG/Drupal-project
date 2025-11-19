<?php

namespace Drupal\shortcut\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\shortcut\ShortcutSetStorageInterface;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin to extract the shortcut set assigned to current user.
 */
#[ViewsArgumentDefault(
  id: 'current_shortcut_set',
  title: new TranslatableMarkup('Shortcut set assigned to current user'),
)]
class CurrentShortcutSet extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * CurrentUser constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\shortcut\ShortcutSetStorageInterface $shortcutSetStorage
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface|null $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ShortcutSetStorageInterface $shortcutSetStorage,
    protected ?AccountInterface $currentUser = NULL,
  ) {
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
      $container->get('entity_type.manager')->getStorage('shortcut_set'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument(): string|int|null {
    $shortcut_set = $this->shortcutSetStorage->getDisplayedToUser($this->currentUser);
    return $shortcut_set->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // @todo it needs to be cached per user.shortcut_set cache context.
    return ['user'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

}
