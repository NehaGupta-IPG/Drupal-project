<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\PageCache;

use Drupal\Core\PageCache\DefaultRequestPolicy;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\dynamic_page_cache\PageCache\RequestPolicy\DefaultRequestPolicy as DynamicPageCacheDefaultRequestPolicy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the deprecated default request policies.
 */
#[Group('PageCache')]
class DefaultRequestPolicyLegacyTest extends UnitTestCase {

  /**
   * Tests the deprecated page cache default request policy.
   */
  #[IgnoreDeprecations]
  public function testPageCacheDefaultRequestPolicy(): void {
    $this->expectUserDeprecationMessage('Drupal\Core\PageCache\DefaultRequestPolicy is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use \Drupal\Core\PageCache\ChainRequestPolicy with tagged services instead. See https://www.drupal.org/node/3579668');

    $policy = new DefaultRequestPolicy($this->createStub(SessionConfigurationInterface::class));
    $this->assertSame(RequestPolicyInterface::DENY, $policy->check(new Request()));
  }

  /**
   * Tests the deprecated Dynamic Page Cache default request policy.
   */
  #[IgnoreDeprecations]
  public function testDynamicPageCacheDefaultRequestPolicy(): void {
    $this->expectUserDeprecationMessage('Drupal\dynamic_page_cache\PageCache\RequestPolicy\DefaultRequestPolicy is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use \Drupal\Core\PageCache\ChainRequestPolicy with tagged services instead. See https://www.drupal.org/node/3579668');

    $policy = new DynamicPageCacheDefaultRequestPolicy();
    $this->assertSame(RequestPolicyInterface::DENY, $policy->check(new Request()));
  }

}
