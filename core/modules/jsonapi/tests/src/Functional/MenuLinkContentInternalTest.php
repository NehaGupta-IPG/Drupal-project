<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * JSON:API integration test for the "MenuLinkContent" content entity type.
 *
 * Using an internal link.
 */
#[Group('jsonapi')]
#[RunTestsInSeparateProcesses]
class MenuLinkContentInternalTest extends MenuLinkContentTest {

  /**
   * {@inheritdoc}
   */
  protected static bool $noViewAccessControl = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method): void {
    $this->grantPermissionsToTestedRole(['administer menu', 'link to any page']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $menu_link = MenuLinkContent::create([
      'id' => 'user_1',
      'title' => 'User 1',
      'description' => 'Link to the user with the id "1"',
      'link' => 'entity:user/1',
      'weight' => 0,
      'menu_name' => 'main',
    ]);
    $menu_link->save();

    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument(): array {
    $base_url = Url::fromUri('base:/jsonapi/menu_link_content/menu_link_content/' . $this->entity->uuid())->setAbsolute();
    $self_url = clone $base_url;
    $version_identifier = 'id:' . $this->entity->getRevisionId();
    $self_url = $self_url->setOption('query', ['resourceVersion' => $version_identifier]);
    $version_query_string = '?resourceVersion=' . urlencode($version_identifier);
    return [
      'jsonapi' => [
        'meta' => [
          'links' => [
            'self' => ['href' => 'https://jsonapi.org/format/1.1/'],
          ],
        ],
        'version' => '1.1',
      ],
      'links' => [
        'self' => ['href' => $base_url->toString()],
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'menu_link_content--menu_link_content',
        'links' => [
          'self' => ['href' => $self_url->toString()],
          'working-copy' => ['href' => (clone $base_url)->setOption('query', ['resourceVersion' => 'rel:working-copy'])->toString()],
        ],
        'attributes' => [
          'bundle' => 'menu_link_content',
          'link' => [
            'uri' => 'entity:user/1',
            'resolvable_uri' => $this->entity->get('link')->first()->get('resolvable_uri')->getValue(),
            'title' => NULL,
            'options' => [],
          ],
          'changed' => (new \DateTime())->setTimestamp($this->entity->getChangedTime())->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'default_langcode' => TRUE,
          'description' => 'Link to the user with the id "1"',
          'enabled' => TRUE,
          'expanded' => FALSE,
          'external' => FALSE,
          'langcode' => 'en',
          'menu_name' => 'main',
          'parent' => NULL,
          'rediscover' => FALSE,
          'title' => 'User 1',
          'weight' => 0,
          'drupal_internal__id' => 1,
          'drupal_internal__revision_id' => 1,
          'revision_created' => (new \DateTime())->setTimestamp((int) $this->entity->getRevisionCreationTime())->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'revision_log_message' => NULL,
          // @todo Attempt to remove this in https://www.drupal.org/project/drupal/issues/2933518.
          'revision_translation_affected' => TRUE,
        ],
        'relationships' => [
          'revision_user' => [
            'data' => NULL,
            'links' => [
              'related' => [
                'href' => $base_url->toString() . '/revision_user' . $version_query_string,
              ],
              'self' => [
                'href' => $base_url->toString() . '/relationships/revision_user' . $version_query_string,
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testRelationships(): void {
    // @see \Drupal\Tests\jsonapi\Functional\ResourceTestBase::doTestRelationshipMutation()
    $this->grantPermissionsToTestedRole(['access user profiles']);
    parent::testRelationships();
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessCacheability() {
    $cacheability = parent::getExpectedUnauthorizedAccessCacheability();
    $cacheability->addCacheContexts(['user']);
    return $cacheability;
  }

}
