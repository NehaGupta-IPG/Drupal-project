<?php

namespace Drupal\user;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\language\AdminLanguageRender;

/**
 * ToolbarLinkBuilder fills out the placeholders generated in user_toolbar().
 */
class ToolbarLinkBuilder implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * ToolbarHandler constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected AccountProxyInterface $account,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Lazy builder callback for rendering toolbar links.
   *
   * @return array
   *   A renderable array as expected by the renderer service.
   */
  public function renderToolbarLinks() {
    $links = [
      'account' => [
        'title' => $this->t('View profile'),
        'url' => Url::fromRoute('user.page'),
        'attributes' => [
          'title' => $this->t('User account'),
        ],
      ],
      'account_edit' => [
        'title' => $this->t('Edit profile'),
        'url' => Url::fromRoute('entity.user.edit_form', ['user' => $this->account->id()]),
        'attributes' => [
          'title' => $this->t('Edit user account'),
        ],
      ],
      'logout' => [
        'title' => $this->t('Log out'),
        'url' => Url::fromRoute('user.logout'),
      ],
    ];
    $build = [
      '#theme' => 'links__toolbar_user',
      '#links' => $links,
      '#attributes' => [
        'class' => ['toolbar-menu'],
      ],
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];

    // Support rendering the links in the user's preferred admin language.
    if ($this->moduleHandler->moduleExists('language')) {
      $build = AdminLanguageRender::applyTo($build);
    }

    return $build;
  }

  /**
   * Lazy builder callback for rendering the username.
   *
   * @return array
   *   A renderable array as expected by the renderer service.
   */
  public function renderDisplayName() {
    return [
      '#plain_text' => $this->account->getDisplayName(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['renderToolbarLinks', 'renderDisplayName'];
  }

}
