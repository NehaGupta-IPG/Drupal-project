<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationSelected;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent as MenuLinkContentPlugin;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
use Drupal\system\Entity\Menu;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that orderedChildIds() sorts by the titles core would show.
 *
 * The site has three languages: the default (en), the current content
 * language (fr) and a third (de) that is weighted ahead of the default, so
 * the fallback walk reaches it first. Every expected order comes from core's
 * sort over loaded entities.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeTitleOrderTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'treegrid',
    'treegrid_reorder',
    'system',
    'user',
  ];

  /**
   * The loader under test.
   */
  private MenuTreeLoader $loader;

  /**
   * The plugin IDs of the fixture links, keyed by default title.
   *
   * @var array<string, string>
   */
  private array $links = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'language']);

    ConfigurableLanguage::createFromLangcode('fr')->setWeight(5)->save();
    ConfigurableLanguage::createFromLangcode('de')->setWeight(-10)->save();
    ConfigurableLanguage::load('en')->setWeight(0)->save();

    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    ContentLanguageSettings::loadByEntityTypeBundle('menu_link_content', 'menu_link_content')
      ->setDefaultLangcode('site_default')
      ->setLanguageAlterable(TRUE)
      ->save();
    $this->container->get('content_translation.manager')->setEnabled('menu_link_content', 'menu_link_content', TRUE);

    $this->config('language.types')
      ->set('configurable', [LanguageInterface::TYPE_INTERFACE, LanguageInterface::TYPE_CONTENT])
      ->set('negotiation.language_content.enabled', [LanguageNegotiationSelected::METHOD_ID => 0])
      ->save();
    $this->config('language.negotiation')->set('selected_langcode', 'fr')->save();
    $request = Request::create('/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $this->setUpCurrentUser([], ['administer menu']);
    $this->container->get('language_negotiator')->setCurrentUser($this->container->get('current_user'));
    $this->container->get('language_manager')->reset();
    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    $this->container->get('plugin.manager.menu.link')->rebuild();

    // The titles of the default language order each pair differently from the
    // titles of the current language.
    $this->createLink('Apple', 10, ['fr' => 'Zebra']);
    $this->createLink('Banana', 10, ['fr' => 'Mango']);
    // Cat has no French translation but a German one, which the fallback walk
    // reaches before the default.
    $this->createLink('Cat', 20, ['de' => 'Zulu']);
    $this->createLink('Dog', 20, []);
    // Eel has an unpublished French translation.
    $this->createLink('Eel', 30, ['fr' => 'Yak'], ['fr']);
    $this->createLink('Fox', 30, []);

    $this->loader = $this->container->get('menu_tree_ui.loader');
  }

  /**
   * The order follows the current content language, as in core.
   */
  public function testOrderMatchesCore(): void {
    $expected = $this->coreOrder();
    $this->assertSame($expected, $this->loader->orderedChildIds('test', ''));

    // The fixture only decides anything if the orders differ per pair.
    $position = array_flip($expected);
    $this->assertLessThan($position[$this->links['Apple']], $position[$this->links['Banana']]);
    $this->assertLessThan($position[$this->links['Cat']], $position[$this->links['Dog']]);
    $this->assertLessThan($position[$this->links['Eel']], $position[$this->links['Fox']]);
  }

  /**
   * Reading the order loads no entities.
   */
  public function testNoEntitiesLoaded(): void {
    $entity_ids = [];
    foreach ($this->links as $plugin_id) {
      $entity_ids[] = $this->container->get('plugin.manager.menu.link')->getDefinition($plugin_id)['metadata']['entity_id'];
    }
    $queue = new \ReflectionProperty(MenuLinkContentPlugin::class, 'entityIdsToLoad');
    $queue->setValue(NULL, []);
    $memory_cache = $this->container->get('entity.memory_cache');
    $memory_cache->deleteAll();

    $this->loader->orderedChildIds('test', '');

    foreach ($entity_ids as $entity_id) {
      $this->assertFalse($memory_cache->get('values:menu_link_content:' . $entity_id));
    }
    $this->assertSame([], $queue->getValue());
  }

  /**
   * A link whose entity rows are gone is still listed.
   */
  public function testOrphanIsListed(): void {
    $orphan = $this->links['Dog'];
    $entity_id = $this->container->get('plugin.manager.menu.link')->getDefinition($orphan)['metadata']['entity_id'];
    $database = $this->container->get('database');
    $database->delete('menu_link_content_data')->condition('id', $entity_id)->execute();
    $database->delete('menu_link_content')->condition('id', $entity_id)->execute();

    $this->assertContains($orphan, $this->loader->orderedChildIds('test', ''));
  }

  /**
   * Returns the top level as core's sort over loaded entities orders it.
   *
   * @return list<string>
   *   The plugin IDs.
   */
  private function coreOrder(): array {
    $params = new MenuTreeParameters();
    $params->setMaxDepth(1);
    $menu_tree = $this->container->get('menu.link_tree');
    $tree = $menu_tree->load('test', $params);
    $tree = $menu_tree->transform($tree, [['callable' => 'menu.default_tree_manipulators:generateIndexAndSort']]);
    return array_values(array_map(
      static fn(MenuLinkTreeElement $element): string => $element->link->getPluginId(),
      $tree,
    ));
  }

  /**
   * Creates a link in the default language with translations.
   *
   * @param string $title
   *   The title in the default language.
   * @param int $weight
   *   The weight.
   * @param array<string, string> $translations
   *   Translated titles, keyed by language code.
   * @param list<string> $unpublished
   *   The language codes of translations to leave unpublished.
   */
  private function createLink(string $title, int $weight, array $translations, array $unpublished = []): void {
    $entity = MenuLinkContent::create([
      'title' => $title,
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'test',
      'weight' => $weight,
      'langcode' => 'en',
    ]);
    foreach ($translations as $langcode => $translated_title) {
      $translation = $entity->addTranslation($langcode, ['title' => $translated_title] + $entity->toArray());
      $translation->set('content_translation_status', !in_array($langcode, $unpublished, TRUE));
    }
    $entity->save();
    $this->links[$title] = $entity->getPluginId();
  }

}
