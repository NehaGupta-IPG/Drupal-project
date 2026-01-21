<?php

declare(strict_types=1);

namespace Drupal\entity_test\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Test entity class.
 */
#[ContentEntityType(
  id: 'entity_test_external',
  label: new TranslatableMarkup('Entity test external'),
  handlers: [
    'access' => 'Drupal\entity_test\EntityTestAccessControlHandler',
    'form' => [
      'default' => 'Drupal\entity_test\EntityTestForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider',
    ],
  ],
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'bundle' => 'type',
    'label' => 'name',
  ],
  links: [
    'canonical' => '/entity_test_external/{entity_test_external}',
    'add-form' => '/entity_test_external/add',
    'edit-form' => '/entity_test_external/{entity_test_external}/edit',
  ],
  admin_permission: 'administer entity_test content',
  base_table: 'entity_test_external',
)]
class EntityTestExternal extends EntityTest {

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = NULL, array $options = []) {
    if ($rel === 'canonical') {
      return Url::fromUri('http://example.com', $options);
    }
    return parent::toUrl($rel, $options);
  }

}
