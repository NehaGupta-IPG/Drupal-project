<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Asset;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\file_test\StreamWrapper\DummyRemoteStreamWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Routing\AssetRoutes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RemoteStreamWrapperAssetsTest extends KernelTestBase {

  protected static $modules = ['language', 'common_test', 'system', 'file'];

  protected AssetResolverInterface $assetResolver;

  protected RendererInterface $renderer;

  protected function setUp(): void {
    parent::setUp();

    $this->assetResolver = $this->container->get('asset.resolver');
    $this->renderer = $this->container->get('renderer');
  }

  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container->getDefinition('stream_wrapper.assets')->setClass(DummyRemoteStreamWrapper::class);
  }

  public function testAssetRoutesWithRemoteStreamWrapper(): void {
    $build['#attached']['library'][] = 'core/drupal.timezone';
    $build['#attached']['library'][] = 'core/drupal.vertical-tabs';
    $assets = AttachedAssets::createFromRenderArray($build);

    [, $footer_js] = $this->assetResolver->getJsAssets($assets, TRUE, \Drupal::languageManager()->getCurrentLanguage());
    $collectionUrl = $footer_js[1]['data'];
    $this->assertStringStartsWith(
      '/' . AssetRoutes::DEFAULT_DIRECTORY_PATH,
      $collectionUrl,
      'Asset collection URL begins with default asset prefix.'
    );
    $kernel = $this->container->get('kernel');
    assert($kernel instanceof DrupalKernelInterface);
    $response = $kernel->handle(Request::create($collectionUrl));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertGreaterThan(
      0,
      mb_strlen($response->getContent()),
      'Response not empty for asset.'
    );
  }

}
