<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Template;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\HtmlAttributes;
use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Twig\Environment;
use Twig\Markup as TwigMarkup;

/**
 * Tests Drupal\Core\Template\Attribute.
 *
 * @see \Drupal\Tests\Component\Utility\HtmlAttributesTest
 */
#[CoversClass(Attribute::class)]
#[Group('Template')]
class AttributeTest extends UnitTestCase {

  /**
   * Tests the interfaces and parent class of Attribute.
   */
  public function testClassHierarchy(): void {
    $attribute = new Attribute();
    $this->assertInstanceOf(HtmlAttributes::class, $attribute);
    $this->assertInstanceOf(MarkupInterface::class, $attribute);
  }

  /**
   * Tests JSON serialization.
   */
  public function testJsonSerialize(): void {
    $attribute = new Attribute(['class' => ['example-class'], 'id' => 'example-id']);
    $this->assertSame(' class="example-class" id="example-id"', $attribute->jsonSerialize());
    $this->assertSame('" class=\"example-class\" id=\"example-id\""', json_encode($attribute));
  }

  /**
   * Tests the JSON Schema attribute on __toString().
   */
  public function testJsonSchema(): void {
    $method = new \ReflectionMethod(Attribute::class, '__toString');
    $attributes = $method->getAttributes(JsonSchema::class);
    $this->assertCount(1, $attributes);
    $this->assertSame(
      ['type' => 'string', 'description' => 'Rendered HTML element attributes'],
      $attributes[0]->newInstance()->getJsonSchema(),
    );
  }

  /**
   * Tests that \Twig\Markup values are converted to plain text.
   */
  public function testTwigMarkup(): void {
    // \Twig\Markup objects are generated when using twig defined variables
    // like `{% set xxx %}Foo{% endset %}`.
    $attribute = new Attribute(['title' => new TwigMarkup('foo', 'UTF-8')]);
    $this->assertSame(' title="foo"', (string) $attribute);
  }

  /**
   * Tests the twig calls to the Attribute.
   *
   * @legacy-covers ::removeClass
   * @legacy-covers ::addClass
   */
  #[DataProvider('providerTestAttributeClassHelpers')]
  public function testTwigAddRemoveClasses(string $template, string $expected, array $seed_attributes = []): void {
    $loader = new StringLoader();
    $twig = new Environment($loader);
    $data = ['attributes' => new Attribute($seed_attributes)];
    $result = $twig->createTemplate($template)->render($data);
    $this->assertEquals($expected, $result);
  }

  /**
   * Provides tests data for testEscaping.
   *
   * @return array
   *   An array of test data each containing of a twig template string,
   *   a resulting string of classes and an optional array of attributes.
   */
  public static function providerTestAttributeClassHelpers(): array {
    // cSpell:disable
    return [
      ["{{ attributes.class }}", ''],
      ["{{ attributes.addClass('everest').class }}", 'everest'],
      ["{{ attributes.addClass(['k2', 'kangchenjunga']).class }}", 'k2 kangchenjunga'],
      ["{{ attributes.addClass('lhotse', 'makalu', 'cho-oyu').class }}", 'lhotse makalu cho-oyu'],
      [
        "{{ attributes.addClass('nanga-parbat').class }}",
        'dhaulagiri manaslu nanga-parbat',
        ['class' => ['dhaulagiri', 'manaslu']],
      ],
      [
        "{{ attributes.removeClass('annapurna').class }}",
        'gasherbrum-i',
        ['class' => ['annapurna', 'gasherbrum-i']],
      ],
      [
        "{{ attributes.removeClass(['broad peak']).class }}",
        'gasherbrum-ii',
        ['class' => ['broad peak', 'gasherbrum-ii']],
      ],
      [
        "{{ attributes.removeClass('gyachung-kang', 'shishapangma').class }}",
        '',
        ['class' => ['shishapangma', 'gyachung-kang']],
      ],
      [
        "{{ attributes.removeClass('nuptse').addClass('annapurna-ii').class }}",
        'himalchuli annapurna-ii',
        ['class' => ['himalchuli', 'nuptse']],
      ],
      // Test for the removal of an empty class name.
      ["{{ attributes.addClass('rakaposhi', '').class }}", 'rakaposhi'],
    ];
    // cSpell:enable
  }

  /**
   * Provides tests data for testMerge.
   *
   * @return array
   *   An array of test data each containing an initial Attribute object, an
   *   Attribute object or array to be merged, and the expected result.
   */
  public static function providerTestMerge(): array {
    return [
      [
        new Attribute([]),
        new Attribute(['class' => ['class1']]),
        new Attribute(['class' => ['class1']]),
      ],
      [
        new Attribute(['class' => ['example-class']]),
        new Attribute(['class' => ['class1']]),
        new Attribute(['class' => ['example-class', 'class1']]),
      ],
      [
        new Attribute(['class' => ['example-class']]),
        new Attribute(['id' => 'foo', 'href' => 'bar']),
        new Attribute([
          'class' => ['example-class'],
          'id' => 'foo',
          'href' => 'bar',
        ]),
      ],
    ];
  }

  /**
   * Tests merge.
   */
  #[DataProvider('providerTestMerge')]
  public function testMerge(Attribute $original, Attribute $merge, Attribute $expected): void {
    $this->assertEquals($expected, $original->merge($merge));
  }

  /**
   * Tests merge argument exception.
   */
  public function testMergeArgumentException(): void {
    $attributes = new Attribute(['class' => ['example-class']]);
    $this->expectException(\TypeError::class);
    $attributes->merge('not an array');
  }

}
