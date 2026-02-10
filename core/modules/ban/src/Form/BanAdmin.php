<?php

declare(strict_types=1);

namespace Drupal\ban\Form;

use Drupal\Core\Form\FormBase;
use Drupal\ban\BanIpManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays banned IP addresses and allows banning/unbanning.
 *
 * @internal
 */
final class BanAdmin extends FormBase {

  /**
   * The ban IP manager.
   *
   * @var \Drupal\ban\BanIpManagerInterface
   */
  protected BanIpManagerInterface $ipManager;

  /**
   * Entity type manager (used to check the allowed list).
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new BanAdmin object.
   */
  public function __construct(BanIpManagerInterface $ip_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->ipManager = $ip_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('ban.ip_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ban_admin';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $default_ip = ''): array {
    // Existing core UI (simplified to essentials).
    $form['ip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IP address'),
      '#default_value' => $default_ip,
      '#required' => TRUE,
    ];

    $form['ban_ip'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
    ];

    // Link to unban routes is kept by core listing; not changed here.

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $ip = (string) $form_state->getValue('ip');

    // Core-style validation: non-empty, valid format.
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
      $form_state->setErrorByName('ip', $this->t('Enter a valid IP address.'));
      return;
    }

    // Already banned?
    if ($this->ipManager->isBanned($ip)) {
      $form_state->setErrorByName('ip', $this->t('This IP address is already banned.'));
      return;
    }

    // NEW: Block banning if the IP is on the allowed list (config entity).
    $ids = $this->entityTypeManager
      ->getStorage('ban_allowed_ip')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('allowedIp', $ip)
      ->execute();

    if (!empty($ids)) {
      $form_state->setErrorByName('ip', $this->t('This IP address is allowed and cannot be banned.'));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ip = (string) $form_state->getValue('ip');
    $this->ipManager->banIp($ip);

    $this->messenger()->addStatus($this->t('The IP address %ip has been banned.', ['%ip' => $ip]));
    $form_state->setRedirectUrl(Url::fromRoute('ban.admin_page'));
  }

}
