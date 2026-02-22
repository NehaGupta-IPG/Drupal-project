<?php

declare(strict_types = 1);

namespace Drupal\ban\Form;

use Drupal\ban\BanIpManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to unblock IP addresses.
 *
 * @internal
 */
final class BanDeleteMultiple extends ConfirmFormBase {

  /**
   * The banned IP addresses.
   *
   * @var array
   */
  protected array $banIps;

  /**
   * Constructs a new BanDelete object.
   *
   * @param \Drupal\ban\BanIpManagerInterface $ipManager
   *   The IP manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   */
  public function __construct(
    protected BanIpManagerInterface $ipManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('ban.ip_manager'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ban_ip_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->formatPlural(count($this->banIps), 'Are you sure you want to unblock %ip_address?', 'Are you sure you want to unblock %ips_amount IP addresses?', [
      '%ip_address' => $this->banIps[0],
      '%ips_amount' => count($this->banIps),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Unblock');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('ban.admin_page');
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $ban_id
   *   The IP address record ID to unblock.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $ban_id = ''): array {
    $this->banIps = $this->tempStoreFactory->get('ban_ip_delete_multiple')->get('selected_ips');
    $form['selected_ips'] = [
      '#theme' => 'item_list',
      '#items' => $this->banIps,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    foreach ($this->banIps as $ip) {
      $this->ipManager->unbanIp($ip);
    }
    $this->tempStoreFactory->get('ban_ip_delete_multiple')->delete('selected_ips');
    $this->logger('user')->notice('Unblocked %ips_amount IP addresses.', ['%ips_amount' => count($this->banIps)]);
    $this->messenger()->addStatus($this->t('The selected IP addresses were unblocked.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
