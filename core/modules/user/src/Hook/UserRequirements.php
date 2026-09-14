<?php

declare(strict_types=1);

namespace Drupal\user\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Requirements for the User module.
 */
class UserRequirements {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $connection,
    protected readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $requirements = [];

    $result = (bool) $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', 0)
      ->range(0, 1)
      ->execute();

    if ($result === FALSE) {
      $requirements['anonymous user'] = [
        'title' => $this->t('Anonymous user'),
        'description' => $this->t('The anonymous user does not exist. See the <a href=":url">restore the anonymous (user ID 0) user record</a> for more information', [
          ':url' => 'https://www.drupal.org/node/1029506',
        ]),
        'severity' => RequirementSeverity::Warning,
      ];
    }

    $query = $this->connection->select('users_field_data');
    $query->addExpression('LOWER(mail)', 'lower_mail');
    $query->isNotNull('mail');
    $query->groupBy('lower_mail');
    $query->groupBy('langcode');
    $query->having('COUNT(uid) > :matches', [':matches' => 1]);
    $conflicts = $query->countQuery()->execute()->fetchField();

    if ($conflicts > 0) {
      $requirements['conflicting emails'] = [
        'title' => $this->t('Conflicting user emails'),
        'description' => $this->t('Some user accounts have email addresses that differ only by case. For example, one account might have alice@example.com and another might have Alice@Example.com. See <a href=":url">Conflicting User Emails</a> for more information.', [
          ':url' => 'https://www.drupal.org/node/3486109',
        ]),
        'severity' => RequirementSeverity::Warning,
      ];
    }

    $fieldUiEnabled = $this->moduleHandler->moduleExists('field_ui');
    $viewModeLabels = $this->entityDisplayRepository->getViewModeOptions('user');
    $displays = [];
    foreach ($this->entityTypeManager->getStorage('entity_view_display')->loadByProperties(['targetEntityType' => 'user']) as $display) {
      assert($display instanceof EntityViewDisplayInterface);
      if (!$display->getComponent('member_for')) {
        continue;
      }
      $mode = $display->getMode();
      $label = $viewModeLabels[$mode] ?? $mode;
      $displays[] = $fieldUiEnabled ? [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $mode === 'default'
          ? Url::fromRoute('entity.entity_view_display.user.default')
          : Url::fromRoute('entity.entity_view_display.user.view_mode', ['view_mode_name' => $mode]),
      ] : ['#plain_text' => $label];
    }

    if ($displays) {
      $requirements['user_member_for_extra_field'] = [
        'title' => $this->t('Deprecated "Member for" extra field'),
        'value' => $this->formatPlural(count($displays), '1 user display', '@count user displays'),
        'description' => [
          'message' => [
            '#markup' => $this->t('The user entity "Member for" display extra field is deprecated in drupal:11.5.0 and removed in drupal:13.0.0. Use the "created" field in the following displays instead. See <a href=":url">the change record</a> for more information.', [
              ':url' => 'https://www.drupal.org/node/3611943',
            ]),
          ],
          'displays' => [
            '#theme' => 'item_list',
            '#items' => $displays,
          ],
        ],
        'severity' => RequirementSeverity::Warning,
      ];
    }

    return $requirements;
  }

}
