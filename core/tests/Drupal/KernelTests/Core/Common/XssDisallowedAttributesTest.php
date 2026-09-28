<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Common;

use Drupal\Component\Utility\Xss;
use Drupal\Core\EventSubscriber\XssDisallowedAttributesSubscriber;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tests hook_xss_disallowed_attributes().
 *
 * @see \Drupal\Component\Utility\Xss::setDisallowedAttributes()
 * @see \Drupal\Core\EventSubscriber\XssDisallowedAttributesSubscriber
 */
#[Group('Common')]
#[RunTestsInSeparateProcesses]
class XssDisallowedAttributesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'xss_test'];

  /**
   * Tests that attributes declared by modules are removed.
   */
  public function testDisallowedAttributes(): void {
    $this->assertSame([], Xss::getDisallowedAttributes());

    $subscriber = $this->container->get(XssDisallowedAttributesSubscriber::class);
    $listeners = $this->container->get('event_dispatcher')->getListeners(KernelEvents::REQUEST);
    $this->assertContains([$subscriber, 'onRequest'], $listeners);

    // Sub-requests do not collect the attributes.
    $kernel = $this->container->get('http_kernel');
    $subscriber->onRequest(new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::SUB_REQUEST));
    $this->assertSame([], Xss::getDisallowedAttributes());

    // The main request collects the attributes from modules.
    $subscriber->onRequest(new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST));
    $this->assertSame(['x-data', 'x-on*'], Xss::getDisallowedAttributes());

    $html = '<a href="/foo" x-data="{}" x-onclick="alert(1)" x-show="open">x</a>';
    $this->assertSame('<a href="/foo" x-show="open">x</a>', Xss::filter($html));
  }

}
