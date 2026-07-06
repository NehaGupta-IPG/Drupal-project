<?php

namespace Drupal\contextual\Plugin\views\area;

use Drupal\Component\Render\MarkupInterface;
use Drupal\contextual\ContextualLinksSerializer;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\TokenizeAreaPluginBase;

/**
 * Views area handler for contextual links.
 *
 * @ingroup views_area_handlers
 */
#[ViewsArea("contextual_links")]
class ContextualLinks extends TokenizeAreaPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ContextualLinksSerializer $contextualLinksSerializer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['content'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['content'] = [
      '#title' => $this->t('Contextual links'),
      '#type' => 'textarea',
      '#default_value' => $this->options['content'],
      '#rows' => 6,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if (!$empty || !empty($this->options['empty'])) {
      $markup = $this->renderTextarea($this->options['content']);
      if ($markup === NULL) {
        return [];
      }
      $links = $this->extractLinks($markup);

      // Links must be a nested array of strings, so that they can be
      // serialized by ContextualLinksSerializer::linksToId().
      // @see \Drupal\contextual\Element\ContextualLinks::preRenderLinks()
      if (!empty($links)) {
        $contextual_links = [
          'contextual' => [
            'route_parameters' => $links,
          ],
        ];

        // Add a placeholder, but no contextual-links region, as this would
        // have no dimensions, and we can leverage the region of the view
        // anyway.
        return [
          '#type' => 'contextual_links_placeholder',
          '#id' => $this->contextualLinksSerializer->linksToId($contextual_links),
        ];
      }
    }

    return [];
  }

  /**
   * Render a text area with \Drupal\Component\Utility\Xss::filterAdmin().
   *
   * @param mixed $value
   *   The view field result value.
   *
   * @return \Drupal\Component\Render\MarkupInterface|null
   *   The renderable views markup.
   */
  public function renderTextarea($value): ?MarkupInterface {
    if ($value) {
      return $this->sanitizeValue($this->tokenizeValue($value), 'xss_admin');
    }
    return NULL;
  }

  /**
   * Parses the content markup to extract anchor links.
   *
   * @param \Drupal\Component\Render\MarkupInterface $markup
   *   View area content to parse.
   *
   * @return array
   *   Array of link data, keyed by a unique link key. Each link is an array
   *   with 'title', 'path' and 'options' keys.
   */
  protected function extractLinks(MarkupInterface $markup): array {
    $links = [];
    $dom = new \DOMDocument();
    // cSpell:disable-next-line
    $domOptions = LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_NOENT;
    $domLoaded = $dom->loadHTML((string) $markup, $domOptions);
    if (!$domLoaded) {
      return [];
    }
    $xpath = new \DOMXPath($dom);
    $domLinks = $xpath->query('//a[@href]');
    $i = 0;
    foreach ($domLinks as $domLink) {
      assert($domLink instanceof \DOMElement);
      $title = $domLink->textContent;
      $href = $domLink->getAttribute('href');
      $link_key = "{$this->view->id()}__{$this->view->current_display}__$i";
      $i++;
      $links[$link_key] = [
        'title' => $title,
        'path' => $href,
        'options' => [],
      ];
    }
    return $links;
  }

}
