<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Component\Plugin\Factory\FactoryInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Core\Render\ElementInfoManager;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Render\Element\RenderElementBase
 * @group Render
 */
class ModernRenderElementTest extends UnitTestCase {

  public function testChildren(): void {
    $factory = $this->createMock(FactoryInterface::class);
    $elementInfoManager = new class ($factory) extends ElementInfoManager {

      public function __construct(protected $factory) {}

    };
    $factory->expects($this->any())
      ->method('createInstance')
      ->willReturnCallback(fn () => new Textfield([], '', NULL, $elementInfoManager));
    $element = [];
    $elementObject = $elementInfoManager->fromRenderable($element);
    for ($i = 0; $i <= 2; $i++) {
      $child = ['#test' => $i];
      $elementObject->addChild("test$i", $child);
      unset($child);
    }
    foreach ([1 => ['test0', 'test1', 'test2'], 2 => ['test0', 'test2']] as $delta => $expectedChildrenKeys) {
      $i = 0;
      foreach ($elementObject->getChildren() as $name => $child) {
        $this->assertSame($name, "test$i");
        $this->assertSame($i, $child->test);
        $i += $delta;
      }
      $this->assertSame(Element::children($elementObject->toRenderable()), $expectedChildrenKeys);
      $elementObject->removeChild('test1');
    }
  }

}
