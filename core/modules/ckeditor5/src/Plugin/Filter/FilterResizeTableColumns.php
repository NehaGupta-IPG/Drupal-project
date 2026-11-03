<?php

namespace Drupal\ckeditor5\Plugin\Filter;

use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Psr\Container\ContainerInterface;

/**
 * Provides a filter to apply resizing of table columns.
 *
 * @Filter(
 *   id = "filter_resize_tablecolumns",
 *   title = @Translation("Resize table columns"),
 *   description = @Translation("Uses a <code>data-resize-width</code> attribute on <code>&lt;col&gt;</code> tags to apply resizing of table columns. This filter needs to run after the <strong>Limit allowed HTML tags and correct faulty HTML</strong> filter."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 99,
 * )
 */
class FilterResizeTableColumns extends FilterBase implements ContainerFactoryPluginInterface {

  protected CKEditor5PluginManagerInterface $ckeditor5PluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->ckeditor5PluginManager = $container->get('plugin.manager.ckeditor5.plugin');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $ckeditor5_plugin = $this->ckeditor5PluginManager
      ->createInstance('ckeditor5_tableColumnResize');
    $ckeditor5_plugin_config = $ckeditor5_plugin->getPluginDefinition()->getCkeditor5Config()['tableColResize'];
    $column_data_attribute = $ckeditor5_plugin_config['dataAttribute'];
    $result = new FilterProcessResult($text);

    if (stristr($text, $column_data_attribute) !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);

      /** @var \DOMNode $node */
      foreach ($xpath->query('//*[@' . $column_data_attribute . ']') as $node) {
        $this->processDomNode($node, $column_data_attribute);
      }

      $result->setProcessedText(Html::serialize($dom));
    }

    return $result;
  }

  /**
   * Adds a style and class attributes to passed DOMNode.
   */
  private function processDomNode(\DOMNode $node, string $attribute) : void {
    [, $attribute_value] = $this->getStyleAttributeFromNode($node, $attribute);
    $node->setAttribute('style', $attribute_value);

    // Set a class that allows targetting resized columns in CSS/JS.
    $node->setAttribute(
      'class',
      $node->getAttribute('class')
        ? $node->getAttribute('class') . ' ckeditor-tablecol-resized'
        : 'ckeditor-tablecol-resized'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
        <p>Applies the resizing of tables and columns when rendering the ckeditor content in Drupal.</p>
        <p>This works via a the <code>data-resize-width</code> attribute on <code><col></code> tags, for example: <code><col data-resize-width="99%"</code>.</p>
      ');
    }
    else {
      return $this->t('Allows resizing of table columns by adding the <code>data-resize-width</code> attribute on <code><col></code> tags, for example: <code><col data-resize-width="99%"</code>.');
    }
  }

  /**
   * Gets $width from style attribute on given node and attribute.
   */
  public function getStyleAttributeFromNode(\DOMNode $node, string $attribute): array {
    $width = $node->getAttribute($attribute);
    $node->removeAttribute($attribute);
    $attribute_value = $node->getAttribute('style');

    // Replace existing width style with new one.
    $styles = \explode(';', $attribute_value);
    $to_replace = '';
    foreach ($styles as $style) {
      if (\mb_strpos($style, 'width') === 0) {
        $to_replace = $style;
        break;
      }
    }
    if ($to_replace) {
      $attribute_value = \str_replace($to_replace, 'width:' . $width, $attribute_value);
    }
    else {
      $attribute_value .= 'width:' . $width . ';';
    }

    return [(int) $width, $attribute_value];
  }

}
