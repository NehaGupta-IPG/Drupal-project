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
   * Constructs a BanMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
   *   The decorated kernel.
   * @param \Drupal\ban\BanIpManagerInterface $banIpManager
   *   The ban IP manager.
   */
  public function __construct(protected HttpKernelInterface $httpKernel, protected BanIpManagerInterface $banIpManager) {
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
