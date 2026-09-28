<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Action;

use Drupal\Core\Action\Plugin\Action\GotoAction;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Action;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tests Goto Action.
 */
#[CoversClass(GotoAction::class)]
#[Group('Action')]
#[RunTestsInSeparateProcesses]
class GotoActionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Tests goto action.
   */
  #[DataProvider('providerGotoAction')]
  public function testGotoAction(string $url, string $expected): void {
    // Test the action's listener without other response subscribers, which may
    // reject external redirects or otherwise change the response.
    $dispatcher = new EventDispatcher();
    $this->container->set('event_dispatcher', $dispatcher);
    $entity = $this->createStub(EntityInterface::class);
    $action = Action::create([
      'id' => 'goto',
      'plugin' => 'action_goto_action',
      'configuration' => ['url' => $url],
    ]);
    $action->save();
    $action->execute([$entity]);

    // Simulate a response event to trigger the redirect.
    $kernel = $this->container->get('http_kernel');
    $request = Request::create('');
    $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response());
    $dispatcher->dispatch($event, KernelEvents::RESPONSE);
    $response = $event->getResponse();
    $this->assertSame(302, $response->getStatusCode());
    $location = $response->headers->get('Location');
    $this->assertNotNull($location);

    // Convert to relative path.
    $base = rtrim(Url::fromUri('base:')->setAbsolute()->toString(), '/');
    if (str_starts_with($location, $base)) {
      $location = substr($location, strlen($base));
    }

    $this->assertSame($expected, $location);

    // Verify that the next request is not redirected by this action.
    $response = new Response();
    $event = new ResponseEvent($kernel, Request::create(''), HttpKernelInterface::MAIN_REQUEST, $response);
    $dispatcher->dispatch($event, KernelEvents::RESPONSE);
    $this->assertSame($response, $event->getResponse());
    $this->assertFalse($response->headers->has('Location'));
  }

  /**
   * Data provider for ::testGotoAction().
   */
  public static function providerGotoAction(): array {
    return [
      '<front>' => ['<front>', '/'],
      'empty string' => ['', '/'],
      'internal path' => ['/user', '/user'],
      'internal path with query and fragment' => ['/user?foo=bar#baz', '/user?foo=bar#baz'],
      'external URL' => ['https://example.com', 'https://example.com'],
    ];
  }

}
