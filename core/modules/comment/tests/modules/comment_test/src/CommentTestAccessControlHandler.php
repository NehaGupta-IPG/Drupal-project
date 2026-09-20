<?php

declare(strict_types=1);

namespace Drupal\comment_test;

use Drupal\comment\CommentAccessControlHandler;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Testing comment access control handler.
 *
 * Wraps the original comment access control handler in order to capture the
 * passed context or create access cache ID for testing purposes.
 *
 * @see \Drupal\Tests\comment\Kernel\CommentCreationAccessTest::testContextForCommentCreationAccessCheck()
 */
class CommentTestAccessControlHandler extends CommentAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    array_walk($context, fn (&$val) => $val = $val instanceof EntityInterface ? "{$val->getEntityTypeId()}:{$val->id()}" : $val);
    \Drupal::state()->set('comment_test.create_access.context', $context);
    \Drupal::state()->set('comment_test.create_access.account', $account->id());
    return parent::checkCreateAccess($account, $context, $entity_bundle);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildCreateAccessCid(array $context, ?string $entity_bundle): ?string {
    $cid = parent::buildCreateAccessCid($context, $entity_bundle);
    // NULL $cid means no caching, but saving null to state storage causes the
    // default to be returned when reading, so we use FALSE.
    $cid = !is_string($cid) ? FALSE : $cid;
    \Drupal::state()->set('comment_test.create_access.cid', $cid);
    return $cid ?: NULL;
  }

}
