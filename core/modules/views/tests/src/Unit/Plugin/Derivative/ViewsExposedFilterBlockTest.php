<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\Derivative;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\Derivative\ViewsExposedFilterBlock;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewExecutableFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ViewsExposedFilterBlock deriver.
 */
#[CoversClass(ViewsExposedFilterBlock::class)]
#[Group('views')]
class ViewsExposedFilterBlockTest extends UnitTestCase {

  /**
   * Creates a base deriver setup.
   */
  protected function createDeriver(ViewExecutable $view_executable): ViewsExposedFilterBlock {
    $view = $this->createMock('\Drupal\views\ViewEntityInterface');
    $view->method('id')->willReturn('test_view');
    $view->method('label')->willReturn('Test View');
    $view->method('status')->willReturn(TRUE);
    $view->method('getExecutable')->willReturn($view_executable);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([$view]);

    $factory = $this->createMock(ViewExecutableFactory::class);
    $factory->method('get')->willReturn($view_executable);

    return new ViewsExposedFilterBlock($factory, $storage);
  }

  /**
   * @covers ::getDerivativeDefinitions
   */
  public function testAdminLabelUsesBlockDescription(): void {
    $delta = 'test_view-page_1';

    $view_executable = $this->getMockBuilder(ViewExecutable::class)
      ->disableOriginalConstructor()
      ->getMock();

    $display = $this->createMock('\Drupal\views\Plugin\views\display\DisplayPluginBase');
    $display->display = [
      'id' => 'page_1',
      'display_title' => 'Page Display',
    ];
    $display->method('usesExposedFormInBlock')->willReturn(TRUE);
    $display->method('getOption')->willReturnMap([
      ['exposed_block', TRUE],
      ['block_description', 'Custom Block Description'],
    ]);

    $view_executable->displayHandlers = [
      'page_1' => $display,
    ];

    $deriver = $this->createDeriver($view_executable);

    $derivatives = $deriver->getDerivativeDefinitions([]);

    $this->assertArrayHasKey($delta, $derivatives);
    $this->assertInstanceOf(
      TranslatableMarkup::class,
      $derivatives[$delta]['admin_label']
    );
    $this->assertEquals(
      'Exposed form: Test View: Custom Block Description',
      (string) $derivatives[$delta]['admin_label']
    );
  }

  /**
   * @covers ::getDerivativeDefinitions
   */
  public function testAdminLabelFallsBackToDisplayTitle(): void {
    $delta = 'test_view-page_1';

    $view_executable = $this->getMockBuilder(ViewExecutable::class)
      ->disableOriginalConstructor()
      ->getMock();

    $display = $this->createMock('\Drupal\views\Plugin\views\display\DisplayPluginBase');
    $display->display = [
      'id' => 'page_1',
      'display_title' => 'Page Display',
    ];
    $display->method('usesExposedFormInBlock')->willReturn(TRUE);
    $display->method('getOption')->willReturnMap([
      ['exposed_block', TRUE],
      ['block_description', NULL],
    ]);

    $view_executable->displayHandlers = [
      'page_1' => $display,
    ];

    $deriver = $this->createDeriver($view_executable);

    $derivatives = $deriver->getDerivativeDefinitions([]);

    $this->assertArrayHasKey($delta, $derivatives);
    $this->assertEquals(
      'Exposed form: Test View: Page Display',
      (string) $derivatives[$delta]['admin_label']
    );
  }

}