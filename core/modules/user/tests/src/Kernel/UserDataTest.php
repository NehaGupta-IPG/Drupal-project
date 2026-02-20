<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test the 'user.data' service.
 *
 * @coversDefaultClass \Drupal\user\UserData
 * @group user
 */
class UserDataTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Test the set()/get() method.
   *
   * @param mixed $data
   *   The data to store/retrieve.
   *
   * @covers ::set
   * @covers ::get
   *
   * @dataProvider providerUserData
   */
  public function testUserData(mixed $data): void {
    /** @var \Drupal\user\UserDataInterface $user_data */
    $user_data = \Drupal::service('user.data');

    $user_data->set('user', 5, 'testUserDataSetValue', $data);

    $result = $user_data->get('user', 5, 'testUserDataSetValue');
    if (!is_object($data)) {
      $this->assertSame($data, $result);
    }
    else {
      $this->assertEquals($data, $result);
    }

    $interim_result = $user_data->get('user', 5);
    $result = $interim_result['testUserDataSetValue'];
    if (!is_object($data)) {
      $this->assertSame($data, $result);
    }
    else {
      $this->assertEquals($data, $result);
    }

    $interim_result = $user_data->get('user', NULL, 'testUserDataSetValue');
    $result = $interim_result['5'];
    if (!is_object($data)) {
      $this->assertSame($data, $result);
    }
    else {
      $this->assertEquals($data, $result);
    }
  }

  /**
   * Provider for testUserData.
   */
  public static function providerUserData(): \Generator {

    yield 'String' => [
      'data' => 'test string',
    ];

    yield 'Integer' => [
      'data' => 12345,
    ];

    yield 'Integer like string' => [
      'data' => '12345',
    ];

    yield 'Float' => [
      'data' => (10 / 3),
    ];

    yield 'Null' => [
      'data' => NULL,
    ];

    yield 'True' => [
      'data' => TRUE,
    ];

    yield 'False' => [
      'data' => FALSE,
    ];

    yield 'Array' => [
      'data' => ['foo' => 'bar'],
    ];

    yield 'Object' => [
      'data' => new UserDataTestObject(),
    ];

  }

  /**
   * Test when no results match.
   */
  public function testUserDataGetNoResults(): void {
    /** @var \Drupal\user\UserDataInterface $user_data */
    $user_data = \Drupal::service('user.data');
    $result = $user_data->get('user', 5, 'testUserDataSetValue');
    $this->assertNull($result);
    $result = $user_data->get('user', 5);
    $this->assertEmpty($result);
    $result = $user_data->get('user', NULL, 'testUserDataSetValue');
    $this->assertEmpty($result);
  }

}

/**
 * Object for validating deserialization.
 */
class UserDataTestObject {
}
