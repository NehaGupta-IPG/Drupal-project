<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Plugin\Block;

use Drupal\Core\Language\Language;
use Drupal\system\Entity\Menu;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\MenuInterface;
use Drupal\system\Menu\LanguageMenuLinkTreeManipulator;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests \Drupal\system\Plugin\Block\SystemMenuBlock translation.
 *
 * @see \Drupal\system\Plugin\Block\SystemMenuBlock
 * @see \Drupal\system\Menu\LanguageMenuLinkTreeManipulator
 */
#[Group('system')]
class SystemMenuBlockTranslationTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'content_translation',
    'language',
    'link',
    'menu_link_content',
    'system',
    'user',
  ];

  /**
   * The menu for testing.
   *
   * @var \Drupal\system\MenuInterface
   */
  protected MenuInterface $menu;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install schemas & config.
    $this->installConfig(['language']);
    $this->installEntitySchema('configurable_language');
    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');

    // Add custom menu.
    $this->menu = Menu::create([
      'id' => 'mock',
      'label' => $this->randomMachineName(16),
      'description' => 'Description text',
    ]);
    $this->menu->save();

    // Make menu content links translatable.
    $this->container->get('content_translation.manager')->setEnabled('menu_link_content', 'menu_link_content', TRUE);
  }

  /**
   * Tests that menu blocks display links in proper language.
   */
  #[CoversMethod(LanguageMenuLinkTreeManipulator::class, "process")]
  public function testMenuBlockTranslation(): void {
    $fr_language = ConfigurableLanguage::createFromLangcode('fr');
    $fr_language->save();
    // Create menu links in each language.
    $languages = [
      'en' => 'English',
      'fr' => 'French',
      Language::LANGCODE_NOT_SPECIFIED => 'Not specified',
      Language::LANGCODE_NOT_APPLICABLE => 'Not applicable',
    ];
    $links = [];
    foreach ($languages as $langcode => $language_name) {
      $link = MenuLinkContent::create([
        'title' => "test $language_name",
        'link' => ['uri' => 'https://www.drupal.org/'],
        'menu_name' => $this->menu->id(),
        'external' => TRUE,
        'bundle' => 'menu_link_content',
        'langcode' => $langcode,
      ]);
      $link->save();
      $links[$langcode] = $link;
    }

    /** @var \Drupal\Core\Block\BlockPluginInterface $block */
    $block = $this->container->get('plugin.manager.block')->createInstance('system_menu_block:' . $this->menu->id());
    $block->setConfigurationValue('hide_untranslated_menu_links', TRUE);

    // If English is the default language the items that should be set are
    // - English
    // - Not specified
    // - Not applicable
    // It should not show French links.
    $build = $block->build();
    $this->assertArrayHasKey('languages:language_content', array_flip($build['#cache']['contexts']));
    $this->assertArrayHasKey('#items', $build);
    $this->assertArrayHasKey($links['en']->getPluginId(), $build['#items']);
    $this->assertArrayNotHasKey($links['fr']->getPluginId(), $build['#items']);
    $this->assertArrayHasKey($links[Language::LANGCODE_NOT_SPECIFIED]->getPluginId(), $build['#items']);
    $this->assertArrayHasKey($links[Language::LANGCODE_NOT_APPLICABLE]->getPluginId(), $build['#items']);

    // If French is the default language the items that should be set are
    // - French
    // - Not specified
    // - Not applicable
    // It should not show English links.
    $this->container->get('language.default')->set($fr_language);
    $this->container->get('language_manager')->reset();
    $build = $block->build();
    $this->assertArrayHasKey('languages:language_content', array_flip($build['#cache']['contexts']));
    $this->assertArrayHasKey('#items', $build);
    $this->assertArrayNotHasKey($links['en']->getPluginId(), $build['#items']);
    $this->assertArrayHasKey($links['fr']->getPluginId(), $build['#items']);
    $this->assertArrayHasKey($links[Language::LANGCODE_NOT_SPECIFIED]->getPluginId(), $build['#items']);
    $this->assertArrayHasKey($links[Language::LANGCODE_NOT_APPLICABLE]->getPluginId(), $build['#items']);
  }

}
