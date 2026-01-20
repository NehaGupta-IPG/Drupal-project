<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Theme\ThemePreprocess;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests item_list elements.
 */
#[Group('Theme')]
class ItemListTest extends UnitTestCase {

  /**
   * Preprocess hook class.
   */
  protected ThemePreprocess $preprocess;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->preprocess = new ThemePreprocess(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(PathMatcherInterface::class),
      $this->createMock(CurrentPathStack::class),
      $this->createMock(LanguageManagerInterface::class),
      $this->createMock(RendererInterface::class),
      $this->createMock(RouteMatchInterface::class),
      $this->createMock(ThemeManagerInterface::class),
      $this->createMock(ThemeSettingsProvider::class)
    );
  }

  /**
   * Tests the item_list preprocess.
   */
  public function testPreprocessItemList(): void {
    $variables = [
      'wrapper_attributes' => [],
      'items' => [
        [
          'markup item' => ['#markup' => 'markup element'],
          'plain text item' => ['#plain_text' => 'plain text element'],
        ],
      ],
    ];

    $this->preprocess->preprocessItemList($variables);
    $this->assertEquals(
      [
        [
          'value' => [
            'markup item' => ['#markup' => 'markup element'],
            'plain text item' => ['#plain_text' => 'plain text element'],
          ],
          'attributes' => new Attribute(),
        ],
      ],
      $variables['items']
    );
  }

}
