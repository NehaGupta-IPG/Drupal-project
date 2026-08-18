<?php

declare(strict_types=1);

namespace Drupal\comment_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\comment\CommentInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the comment_test.module.
 */
class CommentTestController extends ControllerBase {

  /**
   * Provides a comment report.
   */
  #[Route(
    path: '/comment/{comment}/report',
    name: 'comment_test.report',
    title: new TranslatableMarkup('Report'),
    requirements: ['_access' => 'TRUE', '_csrf_token' => 'TRUE'],
  )]
  public function commentReport(CommentInterface $comment) {
    return ['#markup' => $this->t('Report for a comment')];
  }

}
