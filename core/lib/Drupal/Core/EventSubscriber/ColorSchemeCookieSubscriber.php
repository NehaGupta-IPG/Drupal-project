<?php

declare(strict_types=1);

namespace Drupal\Core\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Carries the color scheme preference of an account to the client.
 *
 * Every page renders with data-color-scheme="auto" on the <html> element, so
 * the markup is the same for every account and needs no cache context. An
 * account that picked 'light' or 'dark' gets that value in a cookie instead,
 * and a script in the head applies it before the stylesheets are requested.
 *
 * The cookie holds 'light' or 'dark' and nothing else. 'auto' is what the
 * server already rendered, so it is expressed by the absence of the cookie.
 * The common case therefore costs no cookie and no client side work.
 *
 * Each response is compared against the preference of the current user, and
 * only a difference is written. That seeds the cookie when an account logs in
 * on a browser it never opened its profile in, and removes the cookie again
 * when the account logs out.
 *
 * @see \Drupal\Core\Render\BareHtmlPageRenderer::systemPageAttachments()
 * @see \Drupal\Core\Theme\ThemePreprocess::preprocessHtml()
 */
class ColorSchemeCookieSubscriber implements EventSubscriberInterface {

  /**
   * The name of the cookie that carries the color scheme preference.
   *
   * The name holds no '.' and no ' ', because PHP replaces both with '_' in
   * the keys of $_COOKIE, and no '[', because PHP reads that as the start of
   * array notation. A name holding any of those is not the key the server
   * reads back: the preference then looks absent on every request, so every
   * response rewrites the cookie and triggers the kill switch, and a cookie
   * that has to go is never cleared.
   *
   * The dotted 'Drupal.*' cookies elsewhere in core either never reach PHP,
   * or read themselves back under the converted name. Spelling this one
   * without a dot keeps the name the server writes and the name it reads the
   * same, so neither side has to know about the conversion.
   *
   * @see \Drupal\Tests\Core\EventSubscriber\ColorSchemeCookieSubscriberTest::testCookieNameSurvivesCookieParsing()
   */
  public const string COOKIE_NAME = 'DrupalColorScheme';

  /**
   * The color schemes that the cookie can carry.
   */
  protected const array CUSTOMIZED_SCHEMES = ['light', 'dark'];

  /**
   * The lifetime of the cookie in seconds.
   */
  protected const int COOKIE_LIFETIME = 31536000;

  /**
   * The color scheme to write, or NULL to use the one of the current user.
   */
  protected ?string $colorScheme = NULL;

  public function __construct(
    protected readonly AccountInterface $currentUser,
    protected readonly KillSwitch $pageCacheKillSwitch,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Sets the color scheme to reconcile the cookie with.
   *
   * @param string $color_scheme
   *   The color scheme, one of 'light', 'dark' or 'auto'.
   *
   * @see \Drupal\user\Hook\ColorSchemeHooks::userUpdate()
   */
  public function setColorScheme(string $color_scheme): void {
    $this->colorScheme = $color_scheme;
  }

  /**
   * Reconciles the cookie with the color scheme preference of the account.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $color_scheme = $this->colorScheme ?? $this->currentUser->getColorScheme();
    $value = in_array($color_scheme, static::CUSTOMIZED_SCHEMES, TRUE) ? $color_scheme : NULL;

    // Read the raw value, because a request can hold anything under this name,
    // including an array. Anything that is not the value the account asks for
    // is replaced, so a stale or manipulated cookie corrects itself.
    $request = $event->getRequest();
    $sent = $request->cookies->all()[static::COOKIE_NAME] ?? NULL;
    if ($sent === $value) {
      return;
    }

    // The response now carries a Set-Cookie header that belongs to this one
    // account. Trigger the kill switch, which denies the response policy of
    // both Internal Page Cache and Dynamic Page Cache, and which makes
    // FinishResponseSubscriber declare the response uncacheable to proxies and
    // browsers as well.
    // @see \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
    $this->pageCacheKillSwitch->trigger();

    // Scope the cookie to the base path, so that a site in a subdirectory
    // keeps the preference to itself.
    $path = $request->getBasePath() . '/';
    $headers = $event->getResponse()->headers;
    if ($value === NULL) {
      $headers->clearCookie(static::COOKIE_NAME, $path, NULL, $request->isSecure(), FALSE, Cookie::SAMESITE_LAX);
      return;
    }
    // The cookie is read by the script in the head, so it is not HTTP only.
    $headers->setCookie(Cookie::create(
      static::COOKIE_NAME,
      $value,
      $this->time->getRequestTime() + static::COOKIE_LIFETIME,
      $path,
      NULL,
      $request->isSecure(),
      FALSE,
      FALSE,
      Cookie::SAMESITE_LAX,
    ));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run before DynamicPageCacheSubscriber::onResponse(), which has priority
    // 7, and before FinishResponseSubscriber::onRespond(), which has priority
    // 0. Both consult the page cache policies that ::onResponse() denies.
    $events[KernelEvents::RESPONSE][] = ['onResponse', 8];
    return $events;
  }

}
