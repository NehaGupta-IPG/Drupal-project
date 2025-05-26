<?php

namespace Drupal\block_content\Plugin\views\area;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an area plugin to display a block add link.
 *
 * @ingroup views_area_handlers
 */
#[ViewsArea("block_content_listing_empty")]
class ListingEmpty extends AreaPluginBase {

  /**
   * Constructs a new ListingEmpty.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Access\AccessManagerInterface $accessManager
   *   The access manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccessManagerInterface $accessManager,
    protected AccountInterface $currentUser,
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
      $container->get('access_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if (!$empty || !empty($this->options['empty'])) {
      /** @var \Drupal\Core\Access\AccessResultInterface|\Drupal\Core\Cache\CacheableDependencyInterface $access_result */
      $access_result = $this->accessManager->checkNamedRoute('block_content.add_page', [], $this->currentUser, TRUE);
      $element = [
        '#markup' => $this->t('Add a <a href=":url">content block</a>.', [':url' => Url::fromRoute('block_content.add_page')->toString()]),
        '#access' => $access_result->isAllowed(),
        '#cache' => [
          'contexts' => $access_result->getCacheContexts(),
          'tags' => $access_result->getCacheTags(),
          'max-age' => $access_result->getCacheMaxAge(),
        ],
      ];
      return $element;
    }
    return [];
  }

}
