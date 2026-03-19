<?php

declare(strict_types=1);

namespace Drupal\Tests\dynamic_page_cache\Unit;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Cache\Context\ContextCacheKeys;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Cache\VariationCache;
use Drupal\Core\Cache\VariationCacheInterface;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\PageCache\ResponsePolicyInterface;
use Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Provides unit tests for the dynamic page cache event subscriber.
 */
#[CoversClass(DynamicPageCacheSubscriber::class)]
#[Group('dynamic_page_cache')]
class DynamicPageCacheSubscriberTest extends UnitTestCase {

  /**
   * The default cache contexts to vary every cache item by.
   *
   * @var string[]
   */
  protected $cacheContexts = [
    'route',
    // Some routes' controllers rely on the request format (they don't have
    // a separate route for each request format). Additionally, a controller
    // may be returning a domain object that a KernelEvents::VIEW subscriber
    // must turn into an actual response, but perhaps a format is being
    // requested that the subscriber does not support.
    // @see \Drupal\Core\EventSubscriber\RenderArrayNonHtmlSubscriber::onResponse()
    'request_format',
  ];

  /**
   * The cache contexts manager mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject&\Drupal\Core\Cache\Context\CacheContextsManager
   */
  protected CacheContextsManager $cacheContextsManager;

  /**
   * The dynamic page cache subscriber under test.
   *
   * @var \Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber
   */
  protected DynamicPageCacheSubscriber $dynamicPageCacheSubscriber;

  /**
   * The memory cache.
   *
   * @var \Drupal\Core\Cache\VariationCacheInterface
   */
  protected VariationCacheInterface $memoryCache;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The request policy mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject&\Drupal\Core\PageCache\RequestPolicyInterface
   */
  protected RequestPolicyInterface $requestPolicy;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The response policy stub.
   *
   * @var \PHPUnit\Framework\MockObject\Stub&\Drupal\Core\PageCache\ResponsePolicyInterface
   */
  protected ResponsePolicyInterface $responsePolicy;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Set up basic data structures for testing.
    $this->request = new Request();

    $this->requestStack = new RequestStack();
    $this->requestStack->push($this->request);

    // Set up mocks used for testing.
    $this->cacheContextsManager = $this->createMock(CacheContextsManager::class);
    $this->cacheContextsManager->expects($this->any())
      ->method('assertValidTokens')
      ->willReturn(TRUE);
    $this->cacheContextsManager->expects($this->any())
      ->method('convertTokensToKeys')
      ->willReturnCallback(function (array $context_tokens): ContextCacheKeys {
        return new ContextCacheKeys($context_tokens);
      });
    $this->cacheContextsManager
      ->expects($this->any())
      ->method('optimizeTokens')
      ->willReturnCallback(function (array $context_tokens): array {
        return $context_tokens;
      });

    $this->requestPolicy = $this->createMock(RequestPolicyInterface::class);
    $this->responsePolicy = $this->createStub(ResponsePolicyInterface::class);

    // Set up a basic memory-based variation cache for testing.
    $this->memoryCache = new VariationCache($this->requestStack, new MemoryBackend(new Time($this->requestStack)), $this->cacheContextsManager);

    // Set up a container for static cache context validation.
    $container = new Container();
    $container->set('cache_contexts_manager', $this->cacheContextsManager);
    $container->set('datetime.time', new Time($this->requestStack));

    \Drupal::setContainer($container);

    // Set up a basic dynamic page cache subscriber for testing.
    //
    // This instance can be replaced in individual test cases as needed, but we
    // provide one with sensible render configuration by default.
    $this->setUpDynamicPageCacheSubscriber([
      'max-age' => 0,
      'contexts' => [
        'session',
        'user',
      ],
      'tags' => [],
    ]);
  }

  /**
   * Set up a dynamic page cache subscriber with the specified renderer config.
   *
   * @param array $renderer_config
   *   The renderer config to pass.
   */
  protected function setUpDynamicPageCacheSubscriber(array $renderer_config): void {
    $this->dynamicPageCacheSubscriber = new DynamicPageCacheSubscriber($this->requestPolicy, $this->responsePolicy, $this->memoryCache, $this->cacheContextsManager, $renderer_config);
  }

  /**
   * Store the supplied response in the cache.
   *
   * @param \Symfony\Component\HttpFoundation\Response&\Drupal\Core\Cache\CacheableResponseInterface $response
   *   The response to store.
   */
  protected function storeResponseInCache(Response&CacheableResponseInterface $response): void {
    $cacheability = $response->getCacheableMetadata();
    $cacheability->addCacheContexts($this->cacheContexts);

    $initial_cacheability = new CacheableMetadata();
    $initial_cacheability->setCacheContexts($this->cacheContexts);

    $this->memoryCache->set(['response'], $response, $cacheability, $initial_cacheability);
  }

  /**
   * Tests response max age correction.
   *
   * @param int $max_age
   *   The initial max age of the response.
   * @param int $delta
   *   The delta to apply to the request time for this test.
   * @param int|false $expected_ttl
   *   The expected time-to-live.
   */
  #[DataProvider('providerTestResponseMaxAgeCorrection')]
  public function testResponseMaxAgeCorrection(int $max_age, int $delta, int|false $expected_ttl): void {
    $cacheability = new CacheableMetadata();
    $cacheability->setCacheMaxAge($max_age);

    $response = new CacheableResponse();
    $response->addCacheableDependency($cacheability);

    // Store a cached response with the specified max age.
    $this->storeResponseInCache($response);
    // Update the request time using the specified age delta.
    $request = $this->requestStack->getCurrentRequest();
    $request->server->set('REQUEST_TIME', $_SERVER['REQUEST_TIME'] + $delta);

    // Set up the mocks used by the subscriber.
    $this->requestPolicy
      ->expects($this->once())
      ->method('check')
      ->willReturn(NULL);

    $kernel = $this->createStub(HttpKernelInterface::class);
    $event = new RequestEvent($kernel, $this->request, HttpKernelInterface::MAIN_REQUEST);

    // Execute the subscriber callback and check the TTL of the response.
    $this->dynamicPageCacheSubscriber->onRequest($event);

    /** @var \Symfony\Component\HttpFoundation\Response&\Drupal\Core\Cache\CacheableResponseInterface|null $response */
    $response = $event->getResponse();

    $response_cacheability = $response?->getCacheableMetadata();
    $response_max_age = $response_cacheability?->getCacheMaxAge();

    $this->assertSame($expected_ttl, $response_max_age ?? FALSE);
  }

  /**
   * Data provider for ::testResponseMaxAgeCorrection().
   *
   * @return array
   *   The test case conditions.
   */
  public static function providerTestResponseMaxAgeCorrection(): array {
    return [
      'Newly-inserted cache item' => [800, 0, 800],
      'Aged cache item' => [800, 500, 300],
      'Expiring cache item' => [800, 800, 0],
      'Expired cache item' => [800, 801, FALSE],
    ];
  }

}
