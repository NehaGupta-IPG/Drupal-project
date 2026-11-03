<?php

declare(strict_types=1);

namespace Drupal\treegrid;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Renders the CSRF header token into a tree's markup.
 *
 * The token that routes requiring the X-CSRF-Token header check is rendered as
 * a placeholder replaced at the last moment of rendering, so the rest of the
 * page stays cacheable. A consumer prints the placeholder returned by
 * addPlaceholder() into the data-treegrid-csrf-token attribute of its
 * <drupal-treegrid>.
 *
 * The token is the same for every route that takes the header, which is also
 * what /session/token returns.
 */
final class TreegridCsrfToken implements TrustedCallbackInterface {

  /**
   * The fixed value the placeholder string is derived from.
   */
  private const PLACEHOLDER_SEED = 'treegrid_csrf_token';

  public function __construct(
    private readonly CsrfTokenGenerator $csrfToken,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Adds the lazy builder to a build array and returns its placeholder string.
   *
   * The string contains only letters, digits, hyphens and underscores, so it
   * is safe inside an HTML attribute.
   *
   * @param array $build
   *   The render array the placeholder's markup is part of.
   *
   * @return string
   *   The placeholder to print where the token belongs.
   */
  public static function addPlaceholder(array &$build): string {
    $placeholder = 'treegrid_csrf_token_' . Crypt::hashBase64(self::PLACEHOLDER_SEED);
    $build['#attached']['placeholders'][$placeholder] = [
      '#lazy_builder' => ['treegrid.csrf_token:render', []],
    ];
    // The value differs between anonymous and authenticated users, as a form's
    // token does.
    $build['#cache']['contexts'][] = 'user.roles:authenticated';
    return $placeholder;
  }

  /**
   * Renders the token. It's a #lazy_builder callback.
   *
   * Anonymous users get an empty value without the token generator being
   * touched: it would store a seed in their session, which stops page caching
   * for them, and the header check does not apply to them.
   *
   * @return array
   *   A render array holding the token, or nothing for an anonymous user.
   */
  public function render(): array {
    if (!$this->currentUser->isAuthenticated()) {
      return [
        '#markup' => '',
        '#cache' => ['contexts' => ['user.roles:authenticated']],
      ];
    }
    return [
      '#markup' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
      '#cache' => ['contexts' => ['session']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['render'];
  }

}
