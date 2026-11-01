<?php

namespace Drupal\Core\Cron\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\CronInterface;
use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Cron handling.
 */
class CronController extends ControllerBase {

  /**
   * The cron service.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * Constructs a CronController object.
   *
   * @param \Drupal\Core\CronInterface $cron
   *   The cron service.
   */
  public function __construct(CronInterface $cron) {
    $this->cron = $cron;
  }

  /**
   * Run Cron once.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A Symfony response object.
   */
  #[Route(
    path: '/cron/{key}',
    name: 'system.cron',
    requirements: ['_access_system_cron' => 'TRUE'],
    options: ['no_cache' => TRUE],
  )]
  public function run() {
    $this->cron->run();

    // HTTP 204 is "No content", meaning "I did what you asked and we're done.".
    return new Response('', 204);
  }

  /**
   * Run cron manually.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A Symfony direct response object.
   */
  #[Route(
    path: '/admin/reports/status/run-cron',
    name: 'system.run_cron',
    requirements: [
      '_permission' => 'administer site configuration',
      '_csrf_token' => 'TRUE',
    ],
    options: ['no_cache' => TRUE],
  )]
  public function runManually() {
    if ($this->cron->run()) {
      $this->messenger()->addStatus($this->t('Cron ran successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Cron run failed.'));
    }

    return $this->redirect('system.status');
  }

}
