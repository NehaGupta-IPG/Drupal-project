<?php

declare(strict_types=1);

namespace Drupal\navigation\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines a user navigation block.
 *
 * @internal
 */
#[Block(
  id: 'navigation_user',
  admin_label: new TranslatableMarkup('User'),
)]
final class NavigationUserBlock extends BlockBase {

  const string NAVIGATION_LINKS_MENU = 'navigation-user-links';

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#create_placeholder' => TRUE,
      '#lazy_builder' => [static::class . '::buildLinks', [$this->configuration['label']]],
      '#cache' => [
        'keys' => ['navigation_user_block'],
      ],
    ];
  }

  /**
   * Lazy builder callback.
   */
  #[TrustedCallback]
  public static function buildLinks(string $label): array {
    $parameters = new MenuTreeParameters();
    $parameters
      ->setMinDepth(0)
      ->setMaxDepth(2)
      ->onlyEnabledLinks();
    /** @var \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree */
    $menu_tree = \Drupal::service('navigation.menu_tree');
    $subtree = $menu_tree->load(static::NAVIGATION_LINKS_MENU, $parameters);

    // Load the current user so that they can be added as a cacheable dependency
    // of the final render array.
    $account = User::load(\Drupal::currentUser()->id());
    if (!$account) {
      // In certain edge case, the user load may fail, such as a late lazy
      // builder in tests when it was already deleted.
      return [
        '#cache' => [
          'contexts' => ['user'],
        ],
      ];
    }

    $menu_definition = [
      'menu_name' => static::NAVIGATION_LINKS_MENU,
      'route_name' => 'user.page',
      'route_parameters' => [],
      'title' => $account->getDisplayName(),
      'description' => '',
      'options' => [],
      'provider' => 'navigation',
      'enabled' => '1',
    ];
    // Create a parent link that serves as a wrapper.
    // If the menu is removed for any reason, this item shows a link to the
    // user profile page as a fallback.
    $link = MenuLinkDefault::create(\Drupal::getContainer(), [], 'navigation.user_links.user.wrapper', $menu_definition);
    $tree = new MenuLinkTreeElement($link, TRUE, 1, FALSE, $subtree);

    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform([$tree], $manipulators);
    $build = $menu_tree->build($tree);
    $build['#title'] = $label;
    static::replaceCurrentUserUrls($build, $account);
    $build['#cache']['contexts'][] = 'user';
    $cacheable_metadata = CacheableMetadata::createFromRenderArray($build);
    $cacheable_metadata->addCacheableDependency($account);
    $cacheable_metadata->applyTo($build);

    return $build;
  }

  /**
   * Replaces redirecting current-user links with canonical destinations.
   *
   * The navigation user menu keeps routes without parameters so the links stay
   * generic. The rendered URLs must match the pages the user lands on so
   * toolbar JavaScript can mark the current item.
   *
   * @param array $build
   *   The built menu render array.
   * @param \Drupal\user\UserInterface $account
   *   The current user account.
   */
  private static function replaceCurrentUserUrls(array &$build, UserInterface $account): void {
    if (empty($build['#items'])) {
      return;
    }

    $replacements = [
      'navigation.user_links.user.wrapper' => 'entity.user.canonical',
      'navigation.user_links.user.page'    => 'entity.user.canonical',
      'navigation.user_links.user.edit'    => 'entity.user.edit_form',
    ];
    static::replaceItemUrls($build['#items'], $replacements, $account->id());
  }

  /**
   * Replaces matching menu item URLs while preserving options.
   *
   * @param array $items
   *   Menu items keyed by plugin ID.
   * @param array $replacements
   *   Map of plugin IDs to replacement route names.
   * @param string $uid
   *   The current user ID.
   */
  private static function replaceItemUrls(array &$items, array $replacements, string $uid): void {
    foreach ($items as $plugin_id => &$item) {
      if (isset($replacements[$plugin_id], $item['url']) && $item['url'] instanceof Url) {
        $item['url'] = Url::fromRoute(
          $replacements[$plugin_id],
          ['user' => $uid],
          $item['url']->getOptions(),
        );
      }
      if (!empty($item['below'])) {
        static::replaceItemUrls($item['below'], $replacements, $uid);
      }
    }
  }

}
