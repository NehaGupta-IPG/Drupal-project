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
  #[DataProvider('findPageDataProvider')]
  public function testFindPage(string $page, mixed $pager_id, int $expected): void {
    $request = Request::create('http://example.com', 'GET', ['page' => $page]);
    $request->setSession(new Session(new MockArraySessionStorage()));

    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);

    $pager_params = $this->container->get('pager.parameters');

    $this->assertEquals($expected, $pager_params->findPage($pager_id));
  }

  /**
   * Data provider for testFindPage().
   */
  public static function findPageDataProvider(): \Generator {
    yield 'find on multi-pager with id 0' => ['page' => '3,10', 'pager_id' => 0, 'expected' => 3];
    yield 'find on multi-pager with id 1' => ['page' => '3,10', 'pager_id' => 1, 'expected' => 10];
    yield 'find on out of range pager id' => ['page' => '3,10', 'pager_id' => 'index not exists', 'expected' => 0];
    yield 'find on empty page number' => ['page' => '3,,10', 'pager_id' => 1, 'expected' => 0];
    yield 'find on float page number' => ['page' => '1.99', 'pager_id' => 0, 'expected' => 1];
    yield 'find on negative page number' => ['page' => '-1', 'pager_id' => 0, 'expected' => 0];
    yield 'find on string page number' => ['page' => 'drupal', 'pager_id' => 0, 'expected' => 0];
    yield 'find on non utf-8 character page number' => ['page' => 'ð', 'pager_id' => 0, 'expected' => 0];
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
