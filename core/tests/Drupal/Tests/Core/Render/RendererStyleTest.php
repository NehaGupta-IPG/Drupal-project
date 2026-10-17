<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Theme\Style\Exception\StyleDefinitionErrorException;
use Drupal\Core\Theme\Style\StyleDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests Drupal\Core\Render\Renderer.
 */
#[CoversClass(Renderer::class)]
#[Group('Render')]
class RendererStyleTest extends RendererTestBase {

  /**
   * Tests element with styles property.
   */
  public function testStylesProperty(): void {
    $element = [
      '#type' => 'container',
      '#styles' => [
        'my_style' => 'option_1',
        'my_style_2' => 'option_2',
      ],
    ];
    $expectedProcessingElement = [
      '#type' => 'container',
      '#styles' => [
        'my_style' => 'option_1',
        'my_style_2' => 'option_2',
      ],
      '#cache' => [
        'contexts' => [
          'languages:language_interface',
          'theme',
        ],
      ],
      '#defaults_loaded' => TRUE,
    ];

    $definition1 = $this->createMock(StyleDefinitionInterface::class);
    $definition1->expects($this->once())
      ->method('applyOrBubbleOnArray')
      ->with('option_1', $expectedProcessingElement);

    $definition2 = $this->createMock(StyleDefinitionInterface::class);
    $definition2->expects($this->once())
      ->method('applyOrBubbleOnArray')
      ->with('option_2', $expectedProcessingElement);

    $this->stylePluginManager
      ->method('getDefinition')
      ->willReturnCallback(static function (string $string) use ($definition1, $definition2) {
        switch ($string) {
          case 'my_style':
            return $definition1;

          case 'my_style_2':
            return $definition2;
        }
        return '';
      });

    $this->assertArrayHasKey('#styles', $element);
    $this->renderer->renderRoot($element);
    $this->assertArrayHasKey('#styles', $element);
    $this->assertTrue($element['#styles_processed']);
  }

  /**
   * Tests that #styles is still available to #pre_render callbacks.
   */
  public function testStylesPropertyAvailableInPreRender(): void {
    $definition = $this->createMock(StyleDefinitionInterface::class);
    $definition->expects($this->once())
      ->method('applyOrBubbleOnArray');

    $this->stylePluginManager
      ->method('getDefinition')
      ->willReturn($definition);

    $styles = [
      'my_style' => 'option_1',
    ];
    $preRenderStyles = NULL;
    $preRenderProcessed = NULL;
    $element = [
      '#type' => 'container',
      '#styles' => $styles,
      '#pre_render' => [
        static function (array $element) use (&$preRenderStyles, &$preRenderProcessed): array {
          $preRenderStyles = $element['#styles'] ?? NULL;
          $preRenderProcessed = $element['#styles_processed'] ?? NULL;
          return $element;
        },
      ],
    ];

    $this->renderer->renderRoot($element);
    $this->assertSame($styles, $preRenderStyles);
    $this->assertTrue($preRenderProcessed);
  }

  /**
   * Tests that styles are not applied again on an already processed element.
   */
  public function testStylesPropertyAlreadyProcessed(): void {
    $definition = $this->createMock(StyleDefinitionInterface::class);
    $definition->expects($this->never())
      ->method('applyOrBubbleOnArray');

    $this->stylePluginManager
      ->method('getDefinition')
      ->willReturn($definition);

    $element = [
      '#type' => 'container',
      '#styles' => [
        'my_style' => 'option_1',
      ],
      '#styles_processed' => TRUE,
    ];

    $this->renderer->renderRoot($element);
    $this->assertArrayHasKey('#styles', $element);
  }

  /**
   * Tests element with styles property and plugin not found exception.
   */
  public function testStylesPropertyPluginNotFound(): void {
    $exception = new PluginNotFoundException('my_style');

    $this->stylePluginManager
      ->method('getDefinition')
      ->willThrowException($exception);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with("Plugin ID 'my_style' was not found.");

    $this->renderer->setLogger($logger);

    $element = [
      '#type' => 'container',
      '#styles' => [
        'my_style' => 'option_1',
      ],
    ];

    $this->renderer->renderRoot($element);
    $this->assertArrayHasKey('#styles', $element);
    $this->assertTrue($element['#styles_processed']);
  }

  /**
   * Tests element with styles property and style definition error exception.
   */
  public function testStylesPropertyStyleDefinitionError(): void {
    $exception = new StyleDefinitionErrorException('message');

    $this->stylePluginManager
      ->method('getDefinition')
      ->willThrowException($exception);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('message');

    $this->renderer->setLogger($logger);

    $element = [
      '#type' => 'container',
      '#styles' => [
        'my_style' => 'option_1',
      ],
    ];

    $this->renderer->renderRoot($element);
    $this->assertArrayHasKey('#styles', $element);
    $this->assertTrue($element['#styles_processed']);
  }

  /**
   * Tests style option precedence while render metadata bubbles.
   */
  public function testStyleAttachmentPrecedence(): void {
    $element = [
      '#attached' => [
        'styles' => [
          'my_style' => 'ancestor_option',
        ],
      ],
      'first' => [
        '#markup' => 'first',
        '#attached' => [
          'styles' => [
            'my_style' => 'first_option',
          ],
        ],
      ],
      'second' => [
        '#markup' => 'second',
        '#attached' => [
          'styles' => [
            'my_style' => 'second_option',
          ],
        ],
      ],
    ];

    $this->renderer->renderRoot($element);
    $this->assertSame('ancestor_option', $element['#attached']['styles']['my_style']);

    $element = [
      'first' => [
        '#markup' => 'first',
        '#attached' => [
          'styles' => [
            'my_style' => 'first_option',
          ],
        ],
      ],
      'second' => [
        '#markup' => 'second',
        '#attached' => [
          'styles' => [
            'my_style' => 'second_option',
          ],
        ],
      ],
    ];

    $this->renderer->renderRoot($element);
    $this->assertSame('second_option', $element['#attached']['styles']['my_style']);
  }

}
