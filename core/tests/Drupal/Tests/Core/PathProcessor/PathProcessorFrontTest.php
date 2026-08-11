<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\PathProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\PathProcessor\PathProcessorFront;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Test front page path processing.
 */
#[CoversClass(PathProcessorFront::class)]
#[Group('PathProcessor')]
class PathProcessorFrontTest extends UnitTestCase {

  /**
   * Tests basic inbound processing functionality.
   */
  #[DataProvider('providerProcessInbound')]
  public function testProcessInbound(string $frontpage_path, string $path, string $expected, array $expected_query = [], array $request_query = []): void {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config = $this->prophesize(ImmutableConfig::class);
    $config_factory->get('system.site')
      ->willReturn($config->reveal());
    $config->get('page.front')
      ->willReturn($frontpage_path);
    $processor = new PathProcessorFront($config_factory->reveal());
    $request = new Request();
    $request->query->replace($request_query);
    $this->assertEquals($expected, $processor->processInbound($path, $request));
    $this->assertEquals($expected_query, $request->query->all());
  }

  /**
   * Inbound paths and expected results.
   */
  public static function providerProcessInbound(): array {
    return [
      'accessing frontpage' => ['/node', '/', '/node'],
      'accessing non frontpage' => ['/node', '/user', '/user'],
      'accessing frontpage with query parameters' => [
        '/node?example=muh',
        '/',
        '/node',
        ['example' => 'muh'],
      ],
      'frontpage with query parameters and request query parameters' => [
        '/node?example=muh',
        '/',
        '/node',
        ['example' => 'muh', 'example2' => 'buh'],
        ['example2' => 'buh'],
      ],
      'frontpage with query parameters and replacement request query parameters' => [
        '/node?example=muh',
        '/',
        '/node',
        ['example' => 'cuh', 'example2' => 'buh'],
        ['example' => 'cuh', 'example2' => 'buh'],
      ],
    ];
  }

  /**
   * Tests inbound failure with broken config.
   */
  public function testProcessInboundBadConfig(): void {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config = $this->prophesize(ImmutableConfig::class);
    $config_factory->get('system.site')
      ->willReturn($config->reveal());
    $config->get('page.front')
      ->willReturn('');
    $processor = new PathProcessorFront($config_factory->reveal());
    $this->expectException(NotFoundHttpException::class);
    $processor->processInbound('/', new Request());
  }

  /**
   * Tests inbound processing with a language-specific frontpage config override.
   */
  public function testProcessInboundWithLanguageConfigOverride(): void {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);

    $override_config = $this->prophesize(ImmutableConfig::class);
    $override_config->get('page.front')
      ->willReturn('/node/111');

    $language_manager = $this->prophesize(ConfigurableLanguageManagerInterface::class);
    $language_manager->getLanguageConfigOverride('fr', 'system.site')
      ->willReturn($override_config->reveal());

    $processor = new PathProcessorFront($config_factory->reveal(), $language_manager->reveal());
    $request = new Request();
    $request->attributes->set('path_langcode', 'fr');

    $this->assertEquals('/node/111', $processor->processInbound('/', $request));
  }

}
