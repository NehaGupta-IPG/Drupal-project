<?php

namespace Drupal\Core\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

final class ArgumentResolver implements ArgumentResolverInterface {

  public function __construct(protected ArgumentResolverInterface $argumentResolver) {

  }

  public function getArguments(Request $request, callable $controller, ?\ReflectionFunctionAbstract $reflector = NULL): array {
    if (is_array($controller) && $controller[0] instanceof ControllerWrapper) {
      return $controller[0]->getArguments();
    }
    return $this->argumentResolver->getArguments($request, $controller, $reflector);
  }

}
