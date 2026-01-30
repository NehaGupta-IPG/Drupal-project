<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\PageCache;

use Drupal\Core\PageCache\ChainRequestPolicy;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests Drupal\Core\PageCache\ChainRequestPolicy.
 */
#[CoversClass(ChainRequestPolicy::class)]
#[Group('PageCache')]
class ChainRequestPolicyTest extends UnitTestCase {

  /**
   * A request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->request = new Request();
  }

  /**
   * Asserts that check() returns NULL if the chain is empty.
   *
   * @legacy-covers ::check
   */
  public function testEmptyChain(): void {
    $policy = new ChainRequestPolicy(new \ArrayIterator([]));
    $result = $policy->check($this->request);
    $this->assertNull($result);
  }

  /**
   * Asserts that check() returns NULL if a rule returns NULL.
   *
   * @legacy-covers ::check
   */
  public function testNullRuleChain(): void {
    $rule = $this->createMock(RequestPolicyInterface::class);
    $rule->expects($this->once())
      ->method('check')
      ->with($this->request)
      ->willReturn(NULL);

    $policy = new ChainRequestPolicy(new \ArrayIterator([$rule]));

    $result = $policy->check($this->request);
    $this->assertNull($result);
  }

  /**
   * Asserts that check() throws an exception if a rule returns an invalid value.
   *
   * @legacy-covers ::check
   */
  #[DataProvider('providerChainExceptionOnInvalidReturnValue')]
  public function testChainExceptionOnInvalidReturnValue(bool|int|\stdClass|array $return_value): void {
    $rule = $this->createMock(RequestPolicyInterface::class);
    $rule->expects($this->once())
      ->method('check')
      ->with($this->request)
      ->willReturn($return_value);

    $policy = new ChainRequestPolicy(new \ArrayIterator([$rule]));

    $this->expectException(\UnexpectedValueException::class);
    $policy->check($this->request);
  }

  /**
   * Provides test data for testChainExceptionOnInvalidReturnValue.
   *
   * @return array
   *   Test input and expected result.
   */
  public static function providerChainExceptionOnInvalidReturnValue(): array {
    return [
      [FALSE],
      [0],
      [1],
      [TRUE],
      [[1, 2, 3]],
      [new \stdClass()],
    ];
  }

  /**
   * Asserts that check() returns ALLOW if any of the rules returns ALLOW.
   *
   * @legacy-covers ::check
   */
  #[DataProvider('providerAllowIfAnyRuleReturnedAllow')]
  public function testAllowIfAnyRuleReturnedAllow(array $return_values): void {
    $rules = [];
    foreach ($return_values as $return_value) {
      $rule = $this->createMock(RequestPolicyInterface::class);
      $rule->expects($this->once())
        ->method('check')
        ->with($this->request)
        ->willReturn($return_value);
      $rules[] = $rule;
    }

    $policy = new ChainRequestPolicy(new \ArrayIterator($rules));

    $actual_result = $policy->check($this->request);
    $this->assertSame(RequestPolicyInterface::ALLOW, $actual_result);
  }

  /**
   * Provides test data for testAllowIfAnyRuleReturnedAllow.
   *
   * @return array
   *   Test input and expected result.
   */
  public static function providerAllowIfAnyRuleReturnedAllow(): array {
    return [
      [[RequestPolicyInterface::ALLOW]],
      [[NULL, RequestPolicyInterface::ALLOW]],
    ];
  }

  /**
   * Asserts that check() returns immediately when a rule returned DENY.
   */
  public function testStopChainOnFirstDeny(): void {
    $rule1 = $this->createMock(RequestPolicyInterface::class);
    $rule1->expects($this->once())
      ->method('check')
      ->with($this->request)
      ->willReturn(RequestPolicyInterface::ALLOW);

    $deny_rule = $this->createMock(RequestPolicyInterface::class);
    $deny_rule->expects($this->once())
      ->method('check')
      ->with($this->request)
      ->willReturn(RequestPolicyInterface::DENY);

    $ignored_rule = $this->createMock(RequestPolicyInterface::class);
    $ignored_rule->expects($this->never())
      ->method('check');

    $policy = new ChainRequestPolicy(new \ArrayIterator([
      $rule1,
      $deny_rule,
      $ignored_rule,
    ]));

    $actual_result = $policy->check($this->request);
    $this->assertSame(RequestPolicyInterface::DENY, $actual_result);
  }

}
