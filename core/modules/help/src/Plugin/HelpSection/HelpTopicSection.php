<?php

namespace Drupal\help\Plugin\HelpSection;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Environment\EnvironmentManagerInterface;
use Drupal\Core\Environment\EnvironmentParameter;
use Drupal\Core\Environment\LanguageSubstitution;
use Drupal\Core\Environment\RenderContextSubstitution;
use Drupal\help\Attribute\HelpSection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\help\SearchableHelpInterface;
use Drupal\help\HelpTopicPluginInterface;
use Drupal\help\HelpTopicPluginManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\RenderContext;

/**
 * Provides the help topics list section for the help page.
 *
 * @internal
 *   Plugin classes are internal.
 */
#[HelpSection(
  id: 'help_topics',
  title: new TranslatableMarkup('Topics'),
  description: new TranslatableMarkup('Topics can be provided by modules or themes. Top-level help topics on your site:'),
  weight: -10
)]
class HelpTopicSection extends HelpSectionPluginBase implements ContainerFactoryPluginInterface, SearchableHelpInterface {

  /**
   * The top level help topic plugins.
   *
   * @var \Drupal\help\HelpTopicPluginInterface[]
   */
  protected $topLevelPlugins;

  /**
   * The merged top level help topic plugins cache metadata.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  protected $cacheableMetadata;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected HelpTopicPluginManagerInterface $pluginManager,
    protected RendererInterface $renderer,
    protected readonly EnvironmentManagerInterface $environmentManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->getCacheMetadata()->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->getCacheMetadata()->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->getCacheMetadata()->getCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  public function listTopics() {
    // Map the top level help topic plugins to a list of topic links.
    return array_map(function (HelpTopicPluginInterface $topic) {
      return $topic->toLink();
    }, $this->getPlugins());
  }

  /**
   * Gets the top level help topic plugins.
   *
   * @return \Drupal\help\HelpTopicPluginInterface[]
   *   The top level help topic plugins.
   */
  protected function getPlugins() {
    if (!isset($this->topLevelPlugins)) {
      $definitions = $this->pluginManager->getDefinitions();

      $this->topLevelPlugins = [];
      // Get all the top level topics and merge their list cache tags.
      foreach ($definitions as $definition) {
        if ($definition['top_level']) {
          $this->topLevelPlugins[$definition['id']] = $this->pluginManager->createInstance($definition['id']);
        }
      }

      // Sort the top level topics by label and, if the labels match, then by
      // plugin ID.
      usort($this->topLevelPlugins, function (HelpTopicPluginInterface $a, HelpTopicPluginInterface $b) {
        $a_label = (string) $a->getLabel();
        $b_label = (string) $b->getLabel();
        if ($a_label === $b_label) {
          return $a->getPluginId() <=> $b->getPluginId();
        }
        return strnatcasecmp($a_label, $b_label);
      });
    }
    return $this->topLevelPlugins;
  }

  /**
   * {@inheritdoc}
   */
  public function listSearchableTopics() {
    $definitions = $this->pluginManager->getDefinitions();
    return array_column($definitions, 'id');
  }

  /**
   * {@inheritdoc}
   */
  public function renderTopicForSearch($topic_id, LanguageInterface $language) {
    $plugin = $this->pluginManager->createInstance($topic_id);
    if (!$plugin) {
      return [];
    }

    // We are rendering this topic for search indexing or search results,
    // possibly in a different language than the current language. The topic
    // title and body come from translatable things in the Twig template, so we
    // need to set the default language to the desired language, render them,
    // then restore the default language so we do not affect other cron
    // processes.
    $environmentParams = [
      new EnvironmentParameter(RenderContextSubstitution::class),
      new EnvironmentParameter(LanguageSubstitution::class, $language->getId()),
    ];
    return $this->environmentManager->executeInEnvironments($environmentParams, function () use ($plugin) {
      $topic = [];

      // Render the title in this language.
      $title_build = [
        'title' => [
          '#type' => '#markup',
          '#markup' => $plugin->getLabel(),
        ],
      ];
      $topic['title'] = $this->renderer->renderInIsolation($title_build);
      $cacheable_metadata = CacheableMetadata::createFromRenderArray($title_build);

      // Render the body in this language. For this, we need to set up a render
      // context, because the Twig plugins that provide the body assumes one
      // is present.
      $context = new RenderContext();
      $build = [
        'body' => $this->renderer->executeInRenderContext($context, [$plugin, 'getBody']),
      ];
      $topic['text'] = $this->renderer->renderInIsolation($build);
      $cacheable_metadata->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
      $cacheable_metadata->addCacheableDependency($plugin);
      if (!$context->isEmpty()) {
        $cacheable_metadata->addCacheableDependency($context->pop());
      }

      // Add the other information.
      $topic['url'] = $plugin->toUrl();
      $topic['cacheable_metadata'] = $cacheable_metadata;

      return $topic;
    });
  }

  /**
   * Gets the merged CacheableMetadata for all the top level help topic plugins.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The merged CacheableMetadata for all the top level help topic plugins.
   */
  protected function getCacheMetadata() {
    if (!isset($this->cacheableMetadata)) {
      $this->cacheableMetadata = new CacheableMetadata();
      foreach ($this->getPlugins() as $plugin) {
        $this->cacheableMetadata->addCacheableDependency($plugin);
      }
    }
    return $this->cacheableMetadata;
  }

}
