<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Unit\Plugin\Core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\Core\Session\UserSessionTest;
use Drupal\user\RoleInterface;

/**
 * @coversDefaultClass \Drupal\user\Entity\User
 * @group user
 */
class UserTest extends UserSessionTest {

  /**
   * {@inheritdoc}
   */
  protected function createUserSession(array $rids = [], $authenticated = FALSE) {
    $roles = [];
    foreach ($rids as $rid) {
      $roles[] = [
        'target_id' => $rid,
      ];
    }
    $values = ['roles' => [LanguageInterface::LANGCODE_DEFAULT => $roles]];

    $user = $this->getMockBuilder('Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->onlyMethods(['id'])
      ->getMock();

    $reflect = new \ReflectionObject($user);
    $property = $reflect->getProperty('values');
    $property->setAccessible(TRUE);
    $property->setValue($user, $values);

    $translations = [LanguageInterface::LANGCODE_DEFAULT => ['status' => ContentEntityBase::TRANSLATION_EXISTING]];
    $reflect = new \ReflectionObject($user);
    $property = $reflect->getProperty('translations');
    $property->setAccessible(TRUE);
    $property->setValue($user, $translations);

    $user->expects($this->any())
      ->method('id')
      // @todo Also test the uid = 1 handling.
      ->willReturn($authenticated ? 2 : 0);

    return $user;
  }

  /**
   * Tests the method getRoles exclude or include locked roles based in param.
   *
   * @see \Drupal\user\Entity\User::getRoles()
   * @covers ::getRoles
   */
  public function testUserGetRoles(): void {
    // Anonymous user.
    $user = $this->createUserSession([]);
    $this->assertEquals([RoleInterface::ANONYMOUS_ID], $user->getRoles());
    $this->assertEquals([], $user->getRoles(TRUE));

    // Authenticated user.
    $user = $this->createUserSession([], TRUE);
    $this->assertEquals([RoleInterface::AUTHENTICATED_ID], $user->getRoles());
    $this->assertEquals([], $user->getRoles(TRUE));
  }

}
