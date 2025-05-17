<?php

declare(strict_types=1);

namespace Drupal\Tests\block\Functional;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests customizations to Block Content form.
 *
 * Tests customizations to the 'Save' button, addition of 'Save and Configure'
 * button, and different redirects by context depending on active theme or if
 * block content was just created.
 *
 * @group block
 * @see \Drupal\block_content\BlockContentForm
 * @see \Drupal\block\Hook\BlockBlockContentHooks::formBlockContentFormAlter
 * @see \Drupal\block\Hook\BlockBlockContentHooks::blockContentSave
 * @see \Drupal\block\Hook\BlockBlockContentHooks::blockContentConfigureBlock()
 */
final class BlockContentSaveFormRedirectTest extends BrowserTestBase {

  private const TEST_THEMES = ['olivero', 'claro', 'stark'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'navigation_variant_test',
  ];

  /**
   * Tests redirects based on the theme associated with a Block content.
   *
   * @param string $defaultTheme
   *   Set the default theme.
   * @param string $testTheme
   *   The theme to add blocks to.
   * @param bool $canAdministerBlocks
   *   Whether the created user has `administer blocks` permission.
   *
   * @see \Drupal\block_content\Controller\BlockContentController::addForm
   * @covers \Drupal\block\Hook\BlockBlockContentHooks::blockContentConfigureBlock
   * @dataProvider providerBlockContentAddTypes
   */
  public function testsBlockContentAddTypes(string $defaultTheme, string $testTheme, bool $canAdministerBlocks): void {
    $isDefaultTheme = $testTheme == $defaultTheme;

    $this->drupalLogin($this->drupalCreateUser([
      'access block library',
      'administer block content',
      ...($canAdministerBlocks ? ['administer blocks'] : []),
    ]));

    $blockContentType1 = BlockContentType::create([
      'id' => $this->randomMachineName(),
      'label' => 'Test block 1',
    ]);
    $blockContentType1->save();
    $blockContentType2 = BlockContentType::create([
      'id' => $this->randomMachineName(),
      'label' => 'Test block 2',
    ]);
    $blockContentType2->save();

    $this->config('system.theme')->set('default', $defaultTheme)->save();

    \Drupal::service(ThemeInstallerInterface::class)->install([$defaultTheme, $testTheme]);
    $this->drupalGet($isDefaultTheme
      ? Url::fromRoute('block.admin_display')
      : Url::fromRoute('block.admin_display_theme', ['theme' => $testTheme]));
    if ($canAdministerBlocks) {
      $this->clickLink('Place block');
      $this->clickLink('Add content block');
      $this->assertSession()->linkExists($blockContentType1->label());
      $this->assertSession()->linkExists($blockContentType2->label());
      $this->clickLink($blockContentType1->label());
    }
    else {
      // User cant access Block Layout without permission.
      $this->assertSession()->statusCodeEquals(403);

      // Access block content form directly:
      $this->drupalGet(Url::fromRoute(
        'block_content.add_form',
        ['block_content_type' => $blockContentType1->id()],
        [
          'query' => [
            'theme' => $testTheme,
            'region' => 'content',
          ],
        ]
      ));
    }

    $this->assertSession()->pageTextContains(sprintf('Add %s content block', $blockContentType1->label()));

    $info = $this->randomMachineName();
    $this->submitForm(['info[0][value]' => $info], 'Save and configure');

    $blockContents = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties(['info' => $info]);
    $this->assertCount(1, $blockContents);
    $blockContent = reset($blockContents);

    // Ensure we ended up add the Block config add form prefilled.
    if ($canAdministerBlocks) {
      $this->assertSession()->addressEquals(Url::fromRoute('block.admin_add', [
        'plugin_id' => sprintf('block_content:%s', $blockContent->uuid()),
        'theme' => $testTheme,
      ]));
      $this->submitForm(['region' => 'content'], 'Save block');
      $this->assertSession()->addressEquals(Url::fromRoute(
        'block.admin_display_theme',
        ['theme' => $testTheme],
        [
          'query' => [
            'block-placement' => sprintf('%s-%s', $testTheme, Html::getClass($info)),
          ],
        ],
      ));
    }
    else {
      // When the user doesn't have permission, they get sent to the collection
      // URL per \Drupal\block_content\BlockContentForm::save(). This ensures
      // the redirect in blockContentConfigureBlock doesn't send a user to a
      // page it cannot access.
      $this->assertSession()->addressEquals(Url::fromRoute('entity.block_content.collection'));
    }
  }

  /**
   * Data provider.
   *
   * @return \Generator
   *   The test data.
   */
  public static function providerBlockContentAddTypes(): \Generator {
    $defaultTheme = static::TEST_THEMES[array_key_first(static::TEST_THEMES)];
    foreach ([FALSE, TRUE] as $canAdministerBlocks) {
      yield from array_map(
        static fn($themeName) => [$defaultTheme, $themeName, $canAdministerBlocks],
        static::TEST_THEMES,
      );
    }
  }

  /**
   * Tests the region value when a new block is saved.
   */
  public function testBlockRegionPlacement(): void {
    $blockContentType = BlockContentType::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
    ]);
    $blockContentType->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access block library',
      'administer block content',
      'administer blocks',
      sprintf('create %s block content', $blockContentType->id()),
    ]));

    $this->drupalGet(Url::fromRoute(
      'block.admin_library',
      ['theme' => 'stark'],
      ['query' => ['region' => 'content']],
    ));

    $this->clickLink('Add content block');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'info[0][value]' => 'foo',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->fieldValueEquals('region', 'content');
  }

}
