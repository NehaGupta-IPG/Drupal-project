<?php

declare(strict_types=1);

namespace Drupal\Tests\breakpoint\Unit;

use Drupal\breakpoint\Breakpoint;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * @coversDefaultClass \Drupal\breakpoint\Breakpoint
 * @group Breakpoint
 */
class BreakpointTest extends UnitTestCase {

  /**
   * The used plugin ID.
   *
   * @var string
   */
  protected $pluginId = 'breakpoint';

  /**
   * The used plugin definition.
   *
   * @var array
   */
  protected $pluginDefinition = [
    'id' => 'breakpoint',
  ];

  /**
   * The breakpoint under test.
   *
   * @var \Drupal\breakpoint\Breakpoint
   */
  protected $breakpoint;

  /**
   * The mocked translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->stringTranslation = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');
  }

  /**
   * Sets up the breakpoint defaults.
   */
  protected function setupBreakpoint(): void {
    $this->breakpoint = new Breakpoint([], $this->pluginId, $this->pluginDefinition);
    $this->breakpoint->setStringTranslation($this->stringTranslation);
  }

  /**
   * @covers ::getLabel
   */
  public function testGetLabel(): void {
    $this->pluginDefinition['label'] = 'Test label';
    $this->setupBreakpoint();
    $this->assertEquals(new TranslatableMarkup('Test label', [], ['context' => 'breakpoint'], $this->stringTranslation), $this->breakpoint->getLabel());
  }

  /**
   * @covers ::getWeight
   */
  public function testGetWeight(): void {
    $this->pluginDefinition['weight'] = '4';
    $this->setupBreakpoint();
    // Assert that the type returned in an integer.
    $this->assertSame(4, $this->breakpoint->getWeight());
  }

  /**
   * @covers ::getMediaQuery
   */
  public function testGetMediaQuery(): void {
    $this->pluginDefinition['mediaQuery'] = 'only screen and (min-width: 1220px)';
    $this->setupBreakpoint();
    $this->assertEquals('only screen and (min-width: 1220px)', $this->breakpoint->getMediaQuery());
  }

  /**
   * @covers ::hasMediaQuery
   * @dataProvider providerHasMediaQuery
   */
  public function testHasMediaQuery(?string $mediaQuery, bool $expectedValue): void {
    $this->pluginDefinition['mediaQuery'] = $mediaQuery;
    $this->setupBreakpoint();
    $this->assertEquals($expectedValue, $this->breakpoint->hasMediaQuery());
  }

  /**
   * Test cases for ::testHasMediaQuery.
   */
  public static function providerHasMediaQuery(): array {
    return [
      'Empty string' => ['', FALSE],
      'NULL' => [NULL, FALSE],
      'Not empty string' => ['not empty string', TRUE],
      'Not empty but "0" falsy string' => ['0', TRUE],
      'Not empty but "FALSE" falsy string' => ['FALSE', TRUE],
    ];
  }

  /**
   * @covers ::getMediaQuery
   * @dataProvider providerGetMediaQueryReturnsTrimmedString
   */
  public function testGetMediaQueryReturnsTrimmedString($defined, $expected): void {
    $this->pluginDefinition['mediaQuery'] = $defined;
    $this->setupBreakpoint();
    $this->assertEquals($expected, $this->breakpoint->getMediaQuery());
  }

  /**
   * Test cases for ::testGetMediaQueryReturnsTrimmedString.
   */
  public static function providerGetMediaQueryReturnsTrimmedString(): array {
    $mediaQuery = 'only screen and (min-width: 1220px)';
    return [
      [$mediaQuery, $mediaQuery],
      [" $mediaQuery", $mediaQuery],
      ["$mediaQuery ", $mediaQuery],
      [" $mediaQuery ", $mediaQuery],
      ["   $mediaQuery   ", $mediaQuery],
    ];
  }

  /**
   * @covers ::getMediaQuery
   */
  public function testGetMediaQueryReturnsStringWhenUndefined(): void {
    $this->pluginDefinition['mediaQuery'] = NULL;
    $this->setupBreakpoint();
    $this->assertSame('', $this->breakpoint->getMediaQuery());
  }

  /**
   * @covers ::getMultipliers
   */
  public function testGetMultipliers(): void {
    $this->pluginDefinition['multipliers'] = ['1x', '2x'];
    $this->setupBreakpoint();
    $this->assertSame(['1x', '2x'], $this->breakpoint->getMultipliers());
  }

  /**
   * @covers ::getProvider
   */
  public function testGetProvider(): void {
    $this->pluginDefinition['provider'] = 'Breakpoint';
    $this->setupBreakpoint();
    $this->assertEquals('Breakpoint', $this->breakpoint->getProvider());
  }

  /**
   * @covers ::getGroup
   */
  public function testGetGroup(): void {
    $this->pluginDefinition['group'] = 'Breakpoint';
    $this->setupBreakpoint();
    $this->assertEquals('Breakpoint', $this->breakpoint->getGroup());
  }

}
