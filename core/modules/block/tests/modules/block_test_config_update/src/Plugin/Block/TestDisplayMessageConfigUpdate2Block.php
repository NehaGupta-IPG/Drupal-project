<?php

declare(strict_types=1);

namespace Drupal\block_test_config_update\Plugin\Block;

use Drupal\Core\Block\Attribute\ConfigUpdate;
use Drupal\Core\Form\FormStateInterface;

/**
 * Block class override that has a schema update with an additional field.
 */
class TestDisplayMessageConfigUpdate2Block extends TestDisplayMessageBlock {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'third_display_message' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $form['third_display_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Third display message'),
      '#default_value' => $this->configuration['third_display_message'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['third_display_message'] = $form_state->getValue('third_display_message');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = parent::build();
    if (!empty($this->configuration['third_display_message'])) {
      $build['message']['#markup'] .= ' ' . $this->configuration['third_display_message'];
    }
    return $build;
  }

  /**
   * Config update function.
   */
  #[ConfigUpdate(11300)]
  public function changeDisplayMessage(): void {
    // This update should not run if the block schema was first updated to 11400
    // in TestDisplayMessageConfigUpdateBlock and saved.
    $this->configuration['display_message'] = 'should not run';
  }

  /**
   * Config update function.
   */
  #[ConfigUpdate(11500)]
  public function setThirdDisplayMessage(): void {
    unset($this->configuration['second_display_message']);
    $this->configuration['third_display_message'] = 'third display message set';
  }

}
