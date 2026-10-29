<?php

declare(strict_types=1);

namespace Drupal\locale\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Field handler to present a link to edit a translation.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("locale_link_edit")]
class LinkEdit extends FieldPluginBase {

  use RedirectDestinationTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->additional_fields['lid'] = 'lid';
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['text'] = ['default' => '', 'translatable' => TRUE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text to display'),
      '#default_value' => $this->options['text'],
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    // Ensure user has access to edit translations.
    return \Drupal::currentUser()->hasPermission('translate interface');
  }

  /**
   * {@inheritdoc}
   */
  public function render($values): string|TranslatableMarkup {
    $value = $this->getValue($values, 'lid');
    return $this->renderLink($this->sanitizeValue($value), $values);
  }

  /**
   * {@inheritdoc}
   */
  protected function renderLink($data, $values): string|TranslatableMarkup {
    $text = !empty($this->options['text']) ? $this->options['text'] : $this->t('edit');

    $this->options['alter']['make_link'] = TRUE;
    $this->options['alter']['path'] = 'admin/build/translate/edit/' . $data;
    $this->options['alter']['query'] = $this->getDestinationArray();

    return $text;
  }

}
