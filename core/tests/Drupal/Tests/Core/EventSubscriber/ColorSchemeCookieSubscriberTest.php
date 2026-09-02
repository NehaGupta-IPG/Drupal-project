<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\EventSubscriber;

use Drupal\Core\EventSubscriber\ColorSchemeCookieSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the name of the color scheme cookie.
 *
 * The subscriber compares the cookie on the request with the preference of the
 * account and only writes a difference, so the whole design rests on the
 * server reading back the name it wrote. Whether it does is a property of the
 * name alone, and one that no unit test of ::onResponse() can establish:
 * Request::create() takes the cookies it is handed, while a real request
 * carries them through the parsing of $_COOKIE, which rewrites some names.
 *
 * @see \Drupal\FunctionalTests\EventSubscriber\ColorSchemeCookieTest::testTheServerReadsBackTheCookieItWrote()
 */
#[CoversClass(ColorSchemeCookieSubscriber::class)]
#[Group('EventSubscriber')]
class ColorSchemeCookieSubscriberTest extends UnitTestCase {

  /**
   * Tests that PHP hands the cookie name to the server unchanged.
   *
   * PHP applies the same name mangling to the keys of $_COOKIE that it applies
   * to a query string, so parse_str() settles the question with the parser
   * that decides it, rather than with a copy of its rules.
   */
  public function testCookieNameSurvivesCookieParsing(): void {
    $name = ColorSchemeCookieSubscriber::COOKIE_NAME;

    parse_str($name . '=dark', $parsed);
    $this->assertSame([$name => 'dark'], $parsed, sprintf(
      'PHP parses a cookie named %s as %s, so the server never reads back '
      . 'the cookie it wrote. It rewrites some characters in the keys of the '
      . 'cookie array, so a cookie name that is read server side may hold no '
      . 'dot, no space and no opening bracket.',
      $name,
      (string) array_key_first($parsed),
    ));

    // A name built from these characters alone is a valid RFC 6265 token, it
    // survives the parsing above, and it is safe to interpolate into the
    // single quoted JavaScript string that the script in the head builds from
    // this constant.
    // @see \Drupal\Core\Render\BareHtmlPageRenderer::systemPageAttachments()
    $this->assertMatchesRegularExpression('/^[A-Za-z0-9_]+$/', $name);
  }

}
