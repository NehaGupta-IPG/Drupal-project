<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update adding the sticky action buttons theme setting.
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class StickyActionButtonsUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installSchema('user', ['users_data']);
    $this->container->get('theme_installer')->install(['default_admin']);
    require_once $this->root . '/core/themes/default_admin/default_admin.post_update.php';
  }

  /**
   * Tests that the setting is derived from the former settings.php flag.
   *
   * @param string|null $flag
   *   The value of the former settings.php flag, or NULL if it is not set.
   * @param bool $expected
   *   The expected value of the sticky_action_buttons theme setting.
   */
  #[DataProvider('providerStickyActionButtonsSetting')]
  public function testStickyActionButtonsSetting(?string $flag, bool $expected): void {
    $this->config('default_admin.settings')
      ->clear('sticky_action_buttons')
      ->save();
    if ($flag !== NULL) {
      $this->setSetting('core_admin_theme_use_sticky_action_buttons', $flag);
    }

    default_admin_post_update_add_sticky_action_buttons_setting();

    $this->assertSame($expected, $this->config('default_admin.settings')->get('sticky_action_buttons'));
  }

  /**
   * Data provider for testStickyActionButtonsSetting().
   *
   * @return array
   *   The test cases.
   */
  public static function providerStickyActionButtonsSetting(): array {
    return [
      'flag not set' => [NULL, FALSE],
      'never' => ['never', FALSE],
      'content forms' => ['content_forms', TRUE],
      'always' => ['always', TRUE],
    ];
  }

  /**
   * Tests that an existing setting is preserved.
   */
  public function testExistingSettingIsPreserved(): void {
    $this->config('default_admin.settings')
      ->set('sticky_action_buttons', TRUE)
      ->save();

    default_admin_post_update_add_sticky_action_buttons_setting();

    $this->assertTrue($this->config('default_admin.settings')->get('sticky_action_buttons'));
  }

}
