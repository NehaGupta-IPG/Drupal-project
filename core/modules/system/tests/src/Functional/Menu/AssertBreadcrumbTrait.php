<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Menu;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;

/**
 * Provides test assertions for verifying breadcrumbs.
 */
trait AssertBreadcrumbTrait {

  use AssertMenuActiveTrailTrait;

  /**
   * Assert that a given path shows certain breadcrumb links.
   *
   * @param \Drupal\Core\Url|string|null $goto
   *   (optional) A path or URL to pass to
   *   \Drupal\Tests\UiHelperTrait::drupalGet() otherwise a NULL value can be
   *   passed.
   * @param array $trail
   *   An associative array whose keys are expected breadcrumb link paths and
   *   whose values are expected breadcrumb link texts (not sanitized).
   * @param string $page_title
   *   (optional) A page title to additionally assert via
   *   \Drupal\Tests\WebAssert::titleEquals(). Without site name suffix.
   * @param array|null $tree
   *   (optional) An associative array whose keys are link paths and whose
   *   values are link titles (not sanitized) of an expected active trail in a
   *   menu tree output on the page.
   * @param bool $last_active
   *   (optional) Whether the last link in $tree is expected to be active (TRUE)
   *   or just to be in the active trail (FALSE).
   * @param string $active_trail_class
   *   (optional) The class of the active trail. Defaults to
   *   'menu-item--active-trail'.
   * @param string $active_class
   *   (optional) The class of the active element. Defaults to 'is-active'.
   */
  protected function assertBreadcrumb($goto, array $trail, ?string $page_title = NULL, array $tree = [], bool $last_active = TRUE, string $active_trail_class = 'menu-item--active-trail', string $active_class = 'is-active'): void {
    if (isset($goto)) {
      $this->drupalGet($goto);
    }
    $this->assertBreadcrumbParts($trail);

    // Additionally assert page title, if given.
    if (isset($page_title)) {
      $this->assertSession()->titleEquals("$page_title | Drupal");
    }

    // Additionally assert active trail in a menu tree output, if given.
    if ($tree) {
      $this->assertMenuActiveTrail($tree, $last_active, $active_trail_class, $active_class);
    }
  }

  /**
   * Assert that a trail exists in the internal browser.
   *
   * @param array $trail
   *   An associative array whose keys are expected breadcrumb link paths and
   *   whose values are expected breadcrumb link texts (not sanitized).
   */
  protected function assertBreadcrumbParts(array $trail): void {
    // Compare paths with actual breadcrumb.
    $parts = $this->getBreadcrumbParts();
    $found = $parts;
    // A differing number of parts can never match, and also guards against an
    // expected "Home" always passing when no parts are left to compare.
    $pass = count($trail) === count($parts);

    if ($pass) {
      foreach ($trail as $path => $title) {
        // If the path is empty, generate the path from the <front> route.  If
        // the path does not start with a leading slash, then run it through
        // Url::fromUri('base:')->toString() to get the correct base
        // prepended.
        if ($path == '') {
          $url = Url::fromRoute('<front>')->toString();
        }
        elseif ($path[0] != '/') {
          $url = Url::fromUri('base:' . $path)->toString();
        }
        else {
          $url = $path;
        }
        $part = array_shift($parts);
        $pass = ($pass && $part['href'] === $url && $part['text'] === Html::escape($title));
      }
    }

    $this->assertTrue($pass, sprintf('Expected breadcrumb %s on %s but found %s.',
      implode(' » ', $trail),
      $this->getUrl(),
      implode(' » ', array_map(function (array $item) {
        return $item['text'];
      }, $found)),
    ));
  }

  /**
   * Returns the breadcrumb contents of the current page in the internal browser.
   */
  protected function getBreadcrumbParts(): array {
    $parts = [];
    $xpath = '//nav[@aria-labelledby="system-breadcrumb"]//ol/li/a';
    if (method_exists($this, 'getNodeElementsByXpath')) {
      $elements = $this->getNodeElementsByXpath('//nav[@aria-labelledby="system-breadcrumb"]//ol/li/a');
    }
    else {
      $elements = $this->xpath($xpath);
    }
    if (!empty($elements)) {
      foreach ($elements as $element) {
        $parts[] = [
          'text' => $element->getText(),
          'href' => $element->getAttribute('href'),
          'title' => $element->getAttribute('title'),
        ];
      }
    }
    return $parts;
  }

}
