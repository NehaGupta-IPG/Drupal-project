<?php

namespace Drupal\system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\system\RevisionUuidUpdater;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FieldValueUpdater extends ControllerBase {

  /**
   * @var \Drupal\system\RevisionUuidUpdater
   */
  protected $revisionUuidUpdater;

  /**
   * @param \Drupal\system\RevisionUuidUpdater $revision_uuid_updater
   */
  public function __construct(RevisionUuidUpdater $revision_uuid_updater) {
    $this->revisionUuidUpdater = $revision_uuid_updater;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('system.revision_uuid_updater')
    );
  }

  /**
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function update() {
    $this->revisionUuidUpdater->updateAll();
    return $this->redirect('system.admin_reports');
  }

}
