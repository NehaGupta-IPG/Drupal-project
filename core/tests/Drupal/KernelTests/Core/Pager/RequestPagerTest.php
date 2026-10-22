<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Pager;

use Drupal\Core\Pager\PagerParameters;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests Drupal\Core\Pager\PagerParameters.
 */
#[CoversClass(PagerParameters::class)]
#[Group('Pager')]
#[RunTestsInSeparateProcesses]
class RequestPagerTest extends KernelTestBase {

  /**
   * Tests find page.
   */
  public function testFindPage(): void {
    $request = Request::create('http://example.com', 'GET', ['page' => '0,10']);
    $request->setSession(new Session(new MockArraySessionStorage()));

    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);

    $pager_params = $this->container->get('pager.parameters');

    $this->assertEquals(10, $pager_params->findPage(1));
  }

  /**
   * Tests find page with invalid page values.
   */
  #[DataProvider('providerFindPageInvalid')]
  public function testFindPageInvalid(string $page, int $expected): void {
    $request = Request::create('http://example.com', 'GET', ['page' => $page]);
    $request->setSession(new Session(new MockArraySessionStorage()));

    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);

    $pager_params = $this->container->get('pager.parameters');

    $this->assertSame($expected, $pager_params->findPage());
  }

  /**
   * Data provider for ::testFindPageInvalid().
   */
  public static function providerFindPageInvalid(): array {
    return [
      'non-numeric' => ['12E082Cqm', 0],
      'too large' => ['99999999999999999999999', 0],
      'negative' => ['-5', 0],
      'decimal' => ['1.5', 0],
      'max int' => [(string) PHP_INT_MAX, PHP_INT_MAX],
    ];
  }

  /**
   * Tests get query parameters.
   */
  public function testGetQueryParameters(): void {
    $test_parameters = [
      'other' => 'arbitrary',
    ];
    $request = Request::create('http://example.com', 'GET', array_merge(['page' => '0,10'], $test_parameters));
    $request->setSession(new Session(new MockArraySessionStorage()));

    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);

    $pager_params = $this->container->get('pager.parameters');

    $this->assertEquals($test_parameters, $pager_params->getQueryParameters());
    $this->assertEquals(0, $pager_params->findPage());
  }

}
