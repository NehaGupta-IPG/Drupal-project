<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Dom\HTMLDocument;
use Drupal\Component\Utility\Html5;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Tests native HTML5 parsing and serialization.
 */
#[CoversClass(Html5::class)]
#[Group('Utility')]
class Html5Test extends TestCase {

  /**
   * Tests snippet normalization and serialization with native HTML5 rules.
   */
  #[TestWith(['', ''])]
  #[TestWith(['<p>Drupal © &amp; →', '<p>Drupal © &amp; →</p>'])]
  #[TestWith(['<p>text <img alt="ao', '<p>text </p>'])]
  #[TestWith([
    '<span data-caption="foo &lt;em&gt;bar&lt;/em&gt;"></span>',
    '<span data-caption="foo <em>bar</em>"></span>',
  ])]
  #[TestWith(["line1\r\nline2&#13;line3", "line1\nline2\nline3"])]
  public function testNormalize(string $html, string $expected): void {
    $this->assertSame($expected, Html5::normalize($html));
    $this->assertSame($expected, Html5::serialize(Html5::load($html)));
  }

  /**
   * Tests that serialization excludes the document wrapper and head.
   */
  public function testSerializeBody(): void {
    $document = HTMLDocument::createFromString('<!DOCTYPE html><html><head><title>Title</title></head><body><p>Body</p></body></html>');
    $this->assertSame('<p>Body</p>', Html5::serialize($document));
  }

}
