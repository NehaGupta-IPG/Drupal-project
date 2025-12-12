<?php

namespace Drupal\contextual\Plugin\views\area;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\views\Plugin\views\area\TokenizeAreaPluginBase;

/**
 * Views area text handler.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("contextual_links")
 */
class ContextualLinks extends TokenizeAreaPluginBase {

  use RedirectDestinationTrait;

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
    // @fixme Add a contextual-links css class to the view.
    if (!$empty || !empty($this->options['empty'])) {
      $markup = $this->renderTextarea($this->options['content']);
      $links = $this->extractLinks($markup);

      // Renders a contextual links placeholder.
      // Links must be a nested array of strings, so that they can be
      // serialized by _contextual_links_to_id.
      // @see \Drupal\contextual\Element\ContextualLinks::preRenderLinks
      if (!empty($links)) {
        $contextual_links = [
          'contextual' => [
            'route_parameters' => $links,
          ],
        ];

        // Add a placeholder, but no contextual-links region, as this would have
        // no dimensions, and we can leverage the region of the view anyway.
        $element = [
          '#type' => 'contextual_links_placeholder',
          '#id' => _contextual_links_to_id($contextual_links),
        ];
        return $element;
      }
      else {
        return '';
      }
    }

    return '';
  }

  /**
   * Render a text area with \Drupal\Component\Utility\Xss::filterAdmin().
   *
   * @param mixed $value
   *   The view field result value.
   *
   * @return MarkupInterface|null
   *   The renderable views markup.
   */
  public function renderTextarea($value): ?MarkupInterface {
    if ($value) {
      return $this->sanitizeValue($this->tokenizeValue($value), 'xss_admin');
    }
    return NULL;
  }

  /**
   * Parses content markup to extra a tag links.
   *
   * @param MarkupInterface $markup
   *   View area content to parse.
   * @return array
   *   Array of link data.
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
      assert($domLink instanceof \DOMNode);
      $title = $domLink->textContent;
      $href = $domLink->attributes['href']->value;
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
