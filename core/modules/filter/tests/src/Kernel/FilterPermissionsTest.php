<?php

declare(strict_types=1);

namespace Drupal\Tests\filter\Kernel;

use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterPermissions;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the dynamic permissions provided by the filter module.
 *
 * @see \Drupal\filter\FilterPermissions
 */
#[Group('filter')]
#[RunTestsInSeparateProcesses]
class FilterPermissionsTest extends EntityKernelTestBase {

  /**
   * A non-fallback text format that exposes a "use" permission.
   */
  protected FilterFormat $format;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->format = FilterFormat::create([
      'format' => 'filtered_test',
      'name' => 'Filtered test',
    ]);
    $this->format->save();
  }

  /**
   * Tests that the "use text format" permission only links when reachable.
   *
   * The permission title links to the text format configuration page, which
   * requires the "administer filters" permission. A user who can administer
   * permissions but not text formats must not be shown a link that would only
   * return "access denied".
   */
  public function testUseFormatPermissionLink(): void {
    $permission = $this->format->getPermissionName();

    // A user who can edit text formats sees a link to the format.
    $this->setCurrentUser($this->createUser(['administer filters']));
    $title = (string) $this->getPermissions()[$permission]['title'];
    $this->assertStringContainsString('<a href="' . $this->format->toUrl()->toString() . '">Filtered test</a>', $title);

    // A user who cannot edit text formats sees the plain label instead.
    $this->setCurrentUser($this->createUser([$permission]));
    $title = (string) $this->getPermissions()[$permission]['title'];
    $this->assertStringNotContainsString('<a ', $title);
    $this->assertSame('Use the Filtered test text format', $title);
  }

  /**
   * Returns the filter module permissions for the current user.
   *
   * @return array
   *   The permissions array keyed by permission name.
   */
  protected function getPermissions(): array {
    return FilterPermissions::create($this->container)->permissions();
  }

}
