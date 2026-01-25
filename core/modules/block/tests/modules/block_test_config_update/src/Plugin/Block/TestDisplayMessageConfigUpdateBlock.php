<?php

declare(strict_types=1);

namespace Drupal\block_test_config_update\Plugin\Block;

use Drupal\Core\Block\Attribute\ConfigUpdate;
use Drupal\Core\Form\FormStateInterface;

/**
 * Block class override that has a schema update with an additional field.
 */
class TestDisplayMessageConfigUpdateBlock extends TestDisplayMessageBlock {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'second_display_message' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $form['second_display_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Second display message'),
      '#default_value' => $this->configuration['second_display_message'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['second_display_message'] = $form_state->getValue('second_display_message');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = parent::build();
    if (!empty($this->configuration['second_display_message'])) {
      $build['message']['#markup'] .= ' ' . $this->configuration['second_display_message'];
    }
    return $build;
  }

  /**
   * Config update function.
   */
  #[ConfigUpdate(11400)]
  public function setSecondDisplayMessage(): void {
    $this->configuration['second_display_message'] = 'second display message set';
  }

}
