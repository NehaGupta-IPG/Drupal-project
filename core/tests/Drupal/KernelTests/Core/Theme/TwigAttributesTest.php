<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Theme;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Twig with Attribute objects.
 */
#[Group('Theme')]
#[RunTestsInSeparateProcesses]
class TwigAttributesTest extends KernelTestBase {

  /**
   * Tests that Attributes are rendered at the correct time within a macro.
   */
  public function testYield(): void {
    /** @var \Drupal\Core\Template\TwigEnvironment $environment */
    $environment = \Drupal::service('twig');

    $template = '{{ var }}{% set var = var + 1 %}{{ var }}';
    $var = 0;
    $this->assertEquals('01', $environment->renderInline($template, ['var' => $var]));
    $this->assertEquals('01', $environment->renderInline($template, ['var' => &$var]));

    $template = '{{ attributes.addClass("test2") }}{% set attributes = attributes.removeClass("test2") %}{{ attributes }}';
    $attributes = new Attribute(['class' => 'test1']);
    $render_in_context = function (TwigEnvironment $environment, string $template, array $variables): string {
      $context = new RenderContext();
      $renderer = \Drupal::service(RendererInterface::class);
      return (string) $renderer->executeInRenderContext($context, static fn() => $environment->renderInline($template, $variables));
    };
    $this->assertEquals(' class="test1 test2" class="test1"', $render_in_context($environment, $template, ['attributes' => $attributes]));

    $template = '{{ _self.macro_test(var) }}{% macro macro_test(var) %}{{ var }}{% set var = var + 1 %}{{ var }}{% endmacro %}';
    $var = 0;
    $this->assertEquals('01', $render_in_context($environment, $template, ['var' => $var]));
    $this->assertEquals('01', $render_in_context($environment, $template, ['var' => &$var]));

    $template = '{{ _self.macro_test(attributes) }}{% macro macro_test(attributes) %}{{ attributes.addClass("test2") }}{% set attributes = attributes.removeClass("test2") %}{{ attributes }}{% endmacro %}';
    $attributes = new Attribute(['class' => 'test1']);
    $this->assertEquals(' class="test1 test2" class="test1"', $render_in_context($environment, $template, ['attributes' => $attributes]));
  }

  /**
   * Tests that Attribute attachments bubble during rendering.
   */
  public function testAttributeAttachmentBubbling(): void {
    $attribute = new Attribute();
    $attribute->setAttachments([
      'styles' => [
        'my_style' => 'my_option',
      ],
    ]);
    $build = [
      '#type' => 'inline_template',
      '#template' => '<div{{ attributes }}>hey there</div>',
      '#context' => [
        'attributes' => $attribute,
      ],
    ];
    $context = new RenderContext();

    $renderer = \Drupal::service(RendererInterface::class);
    $output = $renderer->executeInRenderContext(
      $context,
      fn () => $renderer->render($build),
    );
    self::assertStringContainsString('div', (string) $output);

    $this->assertCount(1, $context);
    $this->assertSame([
      'styles' => [
        'my_style' => 'my_option',
      ],
    ], $context->pop()->getAttachments());
  }

}
