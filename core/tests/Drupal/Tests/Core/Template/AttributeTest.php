<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Template;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\HtmlAttribute\Attribute as ComponentAttribute;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Core\Theme\Style\StyleDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Twig\Environment;
use Twig\Markup as TwigMarkup;

/**
 * Tests Drupal\Core\Template\Attribute.
 *
 * @see \Drupal\Tests\Component\Utility\HtmlAttribute\AttributeTest
 */
#[CoversClass(Attribute::class)]
#[Group('Template')]
class AttributeTest extends UnitTestCase {

  /**
   * Tests the interfaces and parent class of Attribute.
   */
  public function testClassHierarchy(): void {
    $attribute = new Attribute();
    $this->assertInstanceOf(ComponentAttribute::class, $attribute);
    $this->assertInstanceOf(MarkupInterface::class, $attribute);
    $this->assertInstanceOf(AttachmentsInterface::class, $attribute);
    $this->assertInstanceOf(CacheableDependencyInterface::class, $attribute);
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
   * Tests adding style.
   *
   * @legacy-covers ::addStyle
   */
  public function testAddStyle(): void {
    $attribute = new Attribute();

    $definition = $this->createMock(StyleDefinitionInterface::class);
    $definition->expects($this->once())
      ->method('applyOrBubbleOnAttribute')
      ->with('option_1', $attribute);

    $attribute->addStyle($definition, 'option_1');
  }

  /**
   * Tests adding a NULL style is a no-op.
   *
   * @legacy-covers ::addStyle
   */
  public function testAddStyleNull(): void {
    $attribute = new Attribute();
    $this->assertSame($attribute, $attribute->addStyle(NULL, 'option_1'));
  }

  /**
   * Tests that casting an attribute to a string never touches the renderer.
   *
   * Attachments only bubble when an Attribute object is printed from within
   * a Twig template, via \Drupal\Core\Template\TwigExtension::escapeFilter().
   * Direct PHP stringification, exercised here, must remain safe outside of
   * any render context and must not attempt to process attachments.
   *
   * @see \Drupal\Tests\Core\Template\TwigExtensionTest::testEscapeWithAttribute()
   */
  public function testPrintWithAttachments(): void {
    $attribute = new Attribute(['class' => ['example-class']]);
    $attribute->setAttachments(['library' => ['core/drupal.ajax']]);

    $this->assertSame(' class="example-class"', (string) $attribute);
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

  /**
   * Tests merge with attachments.
   *
   * @legacy-covers ::merge
   */
  public function testMergeWithAttachments(): void {
    $attribute1 = new Attribute();
    $attribute1->addAttachments([
      'library' => [
        'my_module/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
      ],
    ]);

    $attribute2 = new Attribute();
    $attribute2->addAttachments([
      'library' => [
        'my_module/my_library',
        'my_module_2/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option_2',
        'my_style_2' => 'my_option',
      ],
    ]);

    $expected = [
      'library' => [
        'my_module/my_library',
        'my_module/my_library',
        'my_module_2/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
        'my_style_2' => 'my_option',
      ],
    ];

    $attribute1->merge($attribute2);
    $this->assertEquals($expected, $attribute1->getAttachments());
  }

  /**
   * Tests setting attachments.
   *
   * @legacy-covers ::setAttachments
   */
  public function testSetAttachments(): void {
    $attribute = new Attribute();

    $this->assertEmpty($attribute->getAttachments());

    $attribute->setAttachments(['foo' => 'bar']);
    $this->assertEquals(['foo' => 'bar'], $attribute->getAttachments());

    $attribute->setAttachments(['foo2' => 'bar']);
    $this->assertEquals(['foo2' => 'bar'], $attribute->getAttachments());
  }

  /**
   * Tests adding attachments.
   *
   * @legacy-covers ::addAttachments
   */
  public function testAddAttachments(): void {
    $attribute = new Attribute();
    $this->assertEmpty($attribute->getAttachments());

    // First addition.
    $attribute->addAttachments([
      'library' => [
        'my_module/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
      ],
    ]);
    $expected = [
      'library' => [
        'my_module/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
      ],
    ];
    $this->assertEquals($expected, $attribute->getAttachments());

    // Second addition, test merging.
    $attribute->addAttachments([
      'library' => [
        'my_module/my_library',
        'my_module_2/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option_2',
        'my_style_2' => 'my_option',
      ],
    ]);
    $expected = [
      'library' => [
        'my_module/my_library',
        'my_module/my_library',
        'my_module_2/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
        'my_style_2' => 'my_option',
      ],
    ];
    $this->assertEquals($expected, $attribute->getAttachments());
  }

}
