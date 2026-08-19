<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Unit;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\PreviewFallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\EventSubscriber\BlockComponentRenderArray;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests Drupal\layout_builder\Section.
 */
#[CoversClass(Section::class)]
#[Group('layout_builder')]
class SectionRenderTest extends UnitTestCase {

  /**
   * Default cache metadata.
   *
   * @var array
   */
  const CACHE_DEFAULT = [
    '#cache' => [
      'contexts' => [],
      'tags' => [],
      'max-age' => -1,
    ],
  ];

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The context manager service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $layout_plugin_manager = $this->prophesize(LayoutPluginManagerInterface::class);
    $this->blockManager = $this->prophesize(BlockManagerInterface::class);
    $this->contextHandler = $this->prophesize(ContextHandlerInterface::class);
    $this->contextRepository = $this->prophesize(ContextRepositoryInterface::class);
    // @todo Refactor this into some better tests in https://www.drupal.org/node/2942605.
    $event_dispatcher = (new \ReflectionClass(EventDispatcher::class))->newInstanceWithoutConstructor();

    $this->account = $this->prophesize(AccountInterface::class);
    $component_subscriber = new BlockComponentRenderArray($this->account->reveal());
    $event_dispatcher->addSubscriber($component_subscriber);

    $module_handler = $this->createStub(ModuleHandlerInterface::class);
    $module_handler->method('alter')
      ->willReturnCallback(function (string|array $hook, mixed &$data, mixed &$context1 = NULL, mixed &$context2 = NULL): void {
        if ($hook === 'layout_builder_section_regions_render_array') {
          foreach ($context1->getComponents() as $component) {
            if ($output = $component->toRenderArray($context2['contexts'], $context2['in_preview'])) {
              $data[$component->getRegion()][$component->getUuid()] = $output;
            }
          }
        }
      });

    $layout = $this->prophesize(LayoutInterface::class);
    $layout->getPluginDefinition()->willReturn(new LayoutDefinition([]));
    $layout->build(Argument::type('array'))->willReturnArgument(0);
    $layout_plugin_manager->createInstance('layout_onecol', [])->willReturn($layout->reveal());

    $container = new ContainerBuilder();
    $container->set('current_user', $this->account->reveal());
    $container->set('plugin.manager.block', $this->blockManager->reveal());
    $container->set('plugin.manager.core.layout', $layout_plugin_manager->reveal());
    $container->set('context.handler', $this->contextHandler->reveal());
    $container->set('context.repository', $this->contextRepository->reveal());
    $container->set('event_dispatcher', $event_dispatcher);
    $container->set('module_handler', $module_handler);
    \Drupal::setContainer($container);
  }

  /**
   * Tests to render array.
   */
  public function testToRenderArray(): void {
    $block_content = ['#markup' => 'The block content.'];
    $placeholder_label = 'Placeholder Label';
    $render_array = [
      '#theme' => 'block',
      '#weight' => 0,
      '#configuration' => [],
      '#plugin_id' => 'block_plugin_id',
      '#base_plugin_id' => 'block_plugin_id',
      '#derivative_plugin_id' => NULL,
      'content' => $block_content,
      '#in_preview' => FALSE,
    ] + static::CACHE_DEFAULT;

    $block = $this->prophesize(BlockPluginInterface::class)->willImplement(PreviewFallbackInterface::class);
    $this->blockManager->createInstance('block_plugin_id', ['id' => 'block_plugin_id'])->willReturn($block->reveal());

    $access_result = AccessResult::allowed();
    $block->access($this->account->reveal(), TRUE)->willReturn($access_result);
    $block->build()->willReturn($block_content);
    $block->getCacheContexts()->willReturn([]);
    $block->getCacheTags()->willReturn([]);
    $block->getCacheMaxAge()->willReturn(Cache::PERMANENT);
    $block->getPluginId()->willReturn('block_plugin_id');
    $block->getBaseId()->willReturn('block_plugin_id');
    $block->getDerivativeId()->willReturn(NULL);
    $block->getConfiguration()->willReturn([]);
    $block->getPreviewFallbackString()->willReturn($placeholder_label);

    $section = [
      new SectionComponent('some_uuid', 'content', ['id' => 'block_plugin_id']),
    ];
    $expected = [
      'content' => [
        'some_uuid' => $render_array,
      ],
    ];
    $result = (new Section('layout_onecol', [], $section))->toRenderArray();
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests to render array access denied.
   */
  public function testToRenderArrayAccessDenied(): void {
    $block = $this->prophesize(BlockPluginInterface::class);
    $this->blockManager->createInstance('block_plugin_id', ['id' => 'block_plugin_id'])->willReturn($block->reveal());

    $access_result = AccessResult::forbidden();
    $block->access($this->account->reveal(), TRUE)->willReturn($access_result);
    $block->build()->shouldNotBeCalled();
    $block->getCacheContexts()->willReturn([]);
    $block->getCacheTags()->willReturn([]);
    $block->getCacheMaxAge()->willReturn(Cache::PERMANENT);

    $section = [
      new SectionComponent('some_uuid', 'content', ['id' => 'block_plugin_id']),
    ];
    $expected = [
      'content' => [
        'some_uuid' => static::CACHE_DEFAULT,
      ],
    ];
    $result = (new Section('layout_onecol', [], $section))->toRenderArray();
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests to render array preview.
   */
  public function testToRenderArrayPreview(): void {
    $block_content = ['#markup' => 'The block content.'];
    $placeholder_label = 'Placeholder Label';
    $render_array = [
      '#theme' => 'block',
      '#weight' => 0,
      '#configuration' => [],
      '#plugin_id' => 'block_plugin_id',
      '#base_plugin_id' => 'block_plugin_id',
      '#derivative_plugin_id' => NULL,
      'content' => $block_content,
      '#attributes' => [
        'data-layout-content-preview-placeholder-label' => $placeholder_label,
      ],
      '#cache' => [
        'contexts' => [],
        'tags' => [],
        'max-age' => 0,
      ],
      '#in_preview' => TRUE,
    ];
    $block = $this->prophesize(BlockPluginInterface::class)->willImplement(PreviewFallbackInterface::class);
    $this->blockManager->createInstance('block_plugin_id', ['id' => 'block_plugin_id'])->willReturn($block->reveal());

    $block->access($this->account->reveal(), TRUE)->shouldNotBeCalled();
    $block->build()->willReturn($block_content);
    $block->getCacheContexts()->willReturn([]);
    $block->getCacheTags()->willReturn([]);
    $block->getCacheMaxAge()->willReturn(Cache::PERMANENT);
    $block->getConfiguration()->willReturn([]);
    $block->getPluginId()->willReturn('block_plugin_id');
    $block->getBaseId()->willReturn('block_plugin_id');
    $block->getDerivativeId()->willReturn(NULL);
    $block->getPreviewFallbackString()->willReturn($placeholder_label);

    $section = [
      new SectionComponent('some_uuid', 'content', ['id' => 'block_plugin_id']),
    ];
    $expected = [
      'content' => [
        'some_uuid' => $render_array,
      ],
    ];
    $result = (new Section('layout_onecol', [], $section))->toRenderArray([], TRUE);
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests to render array empty.
   */
  public function testToRenderArrayEmpty(): void {
    $section = [];
    $expected = [];
    $result = (new Section('layout_onecol', [], $section))->toRenderArray();
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests context aware block.
   *
   * @legacy-covers ::toRenderArray
   */
  public function testContextAwareBlock(): void {
    $block_content = ['#markup' => 'The block content.'];
    $placeholder_label = 'Placeholder Label';
    $render_array = [
      '#theme' => 'block',
      '#weight' => 0,
      '#configuration' => [],
      '#plugin_id' => 'block_plugin_id',
      '#base_plugin_id' => 'block_plugin_id',
      '#derivative_plugin_id' => NULL,
      'content' => $block_content,
      '#in_preview' => FALSE,
    ] + static::CACHE_DEFAULT;

    $block = $this->prophesize(BlockPluginInterface::class)
      ->willImplement(ContextAwarePluginInterface::class)
      ->willImplement(PreviewFallbackInterface::class);
    $this->blockManager->createInstance('block_plugin_id', ['id' => 'block_plugin_id'])->willReturn($block->reveal());

    $access_result = AccessResult::allowed();
    $block->access($this->account->reveal(), TRUE)->willReturn($access_result);
    $block->build()->willReturn($block_content);
    $block->getCacheContexts()->willReturn([]);
    $block->getCacheTags()->willReturn([]);
    $block->getCacheMaxAge()->willReturn(Cache::PERMANENT);
    $block->getContextMapping()->willReturn([]);
    $block->getPluginId()->willReturn('block_plugin_id');
    $block->getBaseId()->willReturn('block_plugin_id');
    $block->getDerivativeId()->willReturn(NULL);
    $block->getConfiguration()->willReturn([]);
    $block->getPreviewFallbackString()->willReturn($placeholder_label);

    $section = [
      new SectionComponent('some_uuid', 'content', ['id' => 'block_plugin_id']),
    ];
    $expected = [
      'content' => [
        'some_uuid' => $render_array,
      ],
    ];
    $result = (new Section('layout_onecol', [], $section))->toRenderArray();
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests to render array missing plugin id.
   */
  public function testToRenderArrayMissingPluginId(): void {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessageIs('No plugin ID specified for component with "some_uuid" UUID');
    (new Section('layout_onecol', [], [new SectionComponent('some_uuid', 'content')]))->toRenderArray();
  }

}
