<?php

namespace Drupal\Core\Path\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Routing\Router;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

use Drupal\path_alias\PathAliasInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Constraint validator to ensure that an alias doesn't clobber system routes.
 */
class PathAliasOverrideConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a new instance of the class.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   * @param \Drupal\Core\Config\ImmutableConfig $settings
   *   The settings configuration.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Symfony\Component\Routing\Router $router
   *   The router service.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected Settings $settings,
    protected LoggerInterface $logger,
    protected Router $router,
    protected AliasManagerInterface $alias_manager,
    protected LanguageManagerInterface $language_manager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) : PathAliasOverrideConstraintValidator {
    return new static(
      $container->get('current_user'),
      $container->get('settings'),
      $container->get('logger.channel.router'),
      $container->get('router.no_access_checks'),
      $container->get('path_alias.manager'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) : void {

    // If the user may override the url aliases, short-circuit the check.
    // There's nothing more to do.
    if ($this->currentUser->hasPermission('override url aliases')) {
      return;
    }

    if ($value instanceof PathAlias) {

      $route_match = $this->checkPathForSystemRoute($value);
      if ($route_match) {
        $this->context->addViolation($constraint->message, ["%alias" => $value->getAlias()]);
      }
    }

  }

  /**
   * Checks if the provided path alias matches an existing system route.
   *
   * Compares the proposed alias path with the stored alias path and verifies
   * if the alias maps to a valid route. If the alias doesn't match the stored
   * path, attempts to match the alias to a system route and handles various
   * exceptions to determine if the path exists.
   *
   * @param \Drupal\path_alias\PathAliasInterface $pathAlias
   *   The path alias object to check.
   *
   * @return bool
   *   TRUE if the alias matches a system route, FALSE otherwise.
   */
  private function checkPathForSystemRoute(PathAliasInterface $pathAlias) : bool {

    $pathExists = FALSE;

    $proposedAliasPath = $this->alias_manager->getPathByAlias($pathAlias->getAlias());
    $aliasedEntityPath = $pathAlias->getPath();

    // We only need to check if the existing (current entity) aliased path
    // does not match the proposed entity check. If the proposed entity
    // path matches the stored path, then we can consider it already valid.
    if ($proposedAliasPath !== $aliasedEntityPath) {

      try {
        // We don't need to do anything with the return value. If no
        // exception is thrown, we know it matches a system route.
        $this->router->match($pathAlias->getAlias());
        $pathExists = TRUE;
      }
      catch (ResourceNotFoundException |
      MethodNotAllowedException) {
        // There's nothing to do with these exceptions
        // as they indicate that a URI doesn't map to an expected route.
      }
      catch (ParamNotConvertedException) {
        // This exception means that Drupal has found a potentially matching route
        // i.e., "node/1701/edit", but that particular id doesn't exist yet. Since
        // it doesn't exist, but otherwise resolves to a route, we need to mark this
        // as reserved.
        $pathExists = TRUE;
      }
      catch (\Exception $e) {
        // Any other exceptions at this point are unexpected, so we're just
        // going to log them and continue on as if the URI doesn't map to a
        // known route.
        $this->logger->notice("An exception {$e->getMessage()} occurred determining if the alias exists.");
      }

    }
    return $pathExists;

  }

}
