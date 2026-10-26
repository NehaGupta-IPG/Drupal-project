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
   * Tests loading a UTF-8 snippet from a stringable object.
   */
  public function testLoad(): void {
    $html = new class implements \Stringable {

      public function __toString(): string {
        return '<p>Drupal © &amp; →';
      }

    };
    $document = Html5::load($html);
    $this->assertInstanceOf(HTMLDocument::class, $document);
    $this->assertNotNull($document->body);
    $this->assertSame('http://www.w3.org/1999/xhtml', $document->body->namespaceURI);
    $this->assertSame('Drupal © & →', $document->body->textContent);
  }

  /**
   * Tests snippet serialization with native HTML5 parsing and escaping rules.
   */
  #[TestWith(['', ''])]
  #[TestWith(['<p>Drupal © &amp; →', '<p>Drupal © &amp; →</p>'])]
  #[TestWith(['<p>text <img alt="ao', '<p>text </p>'])]
  #[TestWith([
    '<span data-caption="foo &lt;em&gt;bar&lt;/em&gt;"></span>',
    '<span data-caption="foo <em>bar</em>"></span>',
  ])]
  #[TestWith(["line1\r\nline2&#13;line3", "line1\nline2\nline3"])]
  public function testSerialize(string $html, string $expected): void {
    $this->assertSame($expected, Html5::serialize(Html5::load($html)));
  }

  /**
   * Tests that serialization excludes the document wrapper and head.
   */
  public function testSerializeBody(): void {
    $document = HTMLDocument::createFromString('<!DOCTYPE html><html><head><title>Title</title></head><body><p>Body</p></body></html>');
    $this->assertSame('<p>Body</p>', Html5::serialize($document));
  }

  /**
   * Tests serialization of a document without a body.
   */
  public function testSerializeWithoutBody(): void {
    $this->assertSame('', Html5::serialize(HTMLDocument::createEmpty()));
  }

}
