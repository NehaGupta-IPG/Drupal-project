<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel\Upload;

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\UserSession;
use Drupal\file\Entity\File;
use Drupal\file\Upload\ManagedFileValueResolver;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the managed_file element's default value resolution.
 */
#[CoversClass(ManagedFileValueResolver::class)]
#[Group('file')]
#[RunTestsInSeparateProcesses]
class ManagedFileValueResolverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Tests that the element's #default_value is used when there is no input.
   */
  public function testNoInputUsesDefaultValue(): void {
    file_put_contents('public://default-value-test.txt', 'test');
    $file = File::create([
      'uri' => 'public://default-value-test.txt',
      'uid' => 0,
      'status' => 1,
    ]);
    $file->save();

    $element = [
      '#parents' => ['managed_file'],
      '#multiple' => FALSE,
      '#extended' => FALSE,
      '#default_value' => [$file->id()],
    ];

    $result = \Drupal::service(ManagedFileValueResolver::class)
      ->resolveValue($element, FALSE, new FormState());

    $this->assertSame([$file->id()], $result['fids']);
  }

  /**
   * Tests that a temporary file owned by another user cannot be reused.
   */
  public function testTemporaryFileOwnedByAnotherUserForcesDefault(): void {
    file_put_contents('public://other-owner-test.txt', 'test');
    $file = File::create([
      'uri' => 'public://other-owner-test.txt',
      'uid' => 2,
    ]);
    $file->save();
    $this->assertTrue($file->isTemporary());

    \Drupal::currentUser()->setAccount(new UserSession(['uid' => 1]));

    $element = [
      '#parents' => ['managed_file'],
      '#multiple' => FALSE,
      '#extended' => FALSE,
    ];

    $result = \Drupal::service(ManagedFileValueResolver::class)
      ->resolveValue($element, ['fids' => (string) $file->id()], new FormState());

    $this->assertSame([], $result['fids']);
  }

}
