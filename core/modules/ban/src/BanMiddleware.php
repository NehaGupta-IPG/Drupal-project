<?php

namespace Drupal\ban;

use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Provides a HTTP middleware to implement IP based banning.
 */
class BanMiddleware implements HttpKernelInterface {

  /**
   * The decorated kernel.
   *
   * @var HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The ban IP manager.
   *
   * @var BanIpManagerInterface
   */
  protected $banIpManager;

  /**
   * Constructs a BanMiddleware object.
   *
   * @param HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param BanIpManagerInterface $manager
   *   The ban IP manager.
   */
  public function __construct(HttpKernelInterface $http_kernel, BanIpManagerInterface $manager) {
    $this->httpKernel = $http_kernel;
    $this->banIpManager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $ip = $request->getClientIp();
    if ($this->banIpManager->isBanned($ip)) {
      return new Response(new FormattableMarkup('@ip has been banned', ['@ip' => $ip]), 403);
    }
    return $this->httpKernel->handle($request, $type, $catch);
  }

}
