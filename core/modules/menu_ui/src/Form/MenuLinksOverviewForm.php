<?php

declare(strict_types=1);

namespace Drupal\menu_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\menu_ui\MenuOverviewBuilder;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists and reorders the links in a single menu.
 *
 * The classic tabledrag overview, on its own page so that a menu's settings
 * (the "Edit" tab) stay separate from its link list (this "Links" tab). See
 * https://www.drupal.org/project/drupal/issues/2520232.
 *
 * @internal
 */
class MenuLinksOverviewForm extends FormBase {

  public function __construct(
    protected MenuOverviewBuilder $overviewBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(MenuOverviewBuilder::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'menu_link_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?MenuInterface $menu = NULL): array {
    // Record where the overview section lives so submit() can find it.
    $form_state->set('menu_overview_form_parents', ['links']);
    $form['links'] = $this->overviewBuilder->build($menu, $form_state);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->overviewBuilder->submit($form, $form_state);
    $this->messenger()->addStatus($this->t('Your configuration has been saved.'));
  }

}
