<?php

namespace Drupal\system\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\Form\ClearCacheForm;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Provides a block to display 'Clear cache' elements.
 */
#[Block(
  id: "system_clear_cache_block",
  admin_label: new TranslatableMarkup("Clear cache")
)]
class ClearCacheBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * phpcs:ignore Commenting.VariableComment
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * phpcs:ignore Commenting.FunctionComment
   */
  #[Required]
  public function setFormBuilder(FormBuilderInterface $formBuilder): void {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->formBuilder->getForm(ClearCacheForm::class);
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer site configuration');
  }

}
