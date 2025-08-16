<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Utility\ArgumentsResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\Mime\Email;

/**
 * A service which renders emails according to an email definition.
 */
class EmailRenderer implements EmailRendererInterface {

  public function __construct(
    protected readonly CallableResolver $callableResolver,
    protected readonly RendererInterface $renderer,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function render(EmailDefinition $definition, array $params, string $langcode): Email {
    // Add defaults from the plugin definition to the params and let modules
    // alter them.
    $params = new EmailControllerParams(
      $definition,
      $params + $definition->getDefaults(),
      ['langcode' => $langcode],
    );
    $this->moduleHandler->alter('email_params', $params);

    // @todo Replace with execution environment once that is available.
    // @see: https://www.drupal.org/i/3536307
    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($params) {
      // @todo Allow contrib and custom code to provide a custom Email class.
      $email = new Email();
      // Build render array.
      $build = $this->build($email, $params);

      // Render the result.
      if (is_array($build)) {
        // Let modules alter the render array.
        $context = [...$params->getArguments(), 'email' => $email];
        $this->moduleHandler->alter('email_build', $build, $context);

        $build['#theme'] = 'email';
        $build['#props'] = $context;
        $email->html((string) $this->renderer->render($build));

        // Make render metadata available to email_alter (contains #attachments
        // and #cache).
        $metadata = BubbleableMetadata::createFromRenderArray($build);
      }
      else {
        $metadata = new BubbleableMetadata();
      }

      // Let modules alter the resulting email.
      $context = [...$params->getArguments(), 'metadata' => $metadata];
      $this->moduleHandler->alter('email', $email, $context);

      return $email;
    });
  }

  /**
   * Call the email controller and return its result.
   *
   * @param \Symfony\Component\Mime\Email $email
   *   The email instance.
   * @param \Drupal\Core\Mailer\EmailControllerParams $params
   *   The email controller parameters.
   *
   * @return mixed
   *   The result returned from the controller.
   */
  protected function build(Email $email, EmailControllerParams $params): mixed {
    if (!$params->hasControllerDefinition()) {
      // If a definition has no controller, the email is rendered using only the
      // template.
      return [];
    }

    // Resolve controller and arguments.
    try {
      $callable = $this->callableResolver->getCallableFromDefinition($params->getControllerDefinition());
    }
    catch (\InvalidArgumentException $e) {
      throw new \InvalidArgumentException(sprintf('The controller for Email "%s" is not callable.', $params->definition->id()), 0, $e);
    }
    $resolver = $this->getArgumentResolver($email, $params);
    $arguments = $resolver->getArguments($callable);

    // Build the render array.
    $build = $callable(...$arguments);
    if ($build instanceof RenderableInterface) {
      $build = $build->toRenderable();
    }

    return $build;
  }

  /**
   * Returns an argument resolver instance suitable for the given params.
   *
   * @param \Symfony\Component\Mime\Email $email
   *   The email instance.
   * @param \Drupal\Core\Mailer\EmailControllerParams $params
   *   The email controller parameters.
   */
  protected function getArgumentResolver(Email $email, EmailControllerParams $params): ArgumentsResolver {
    $scalars = [];
    $objects = [
      'email' => $email,
    ];
    $wildcards = [];

    foreach ($params->getArguments() as $key => $value) {
      if (is_object($value)) {
        $objects[$key] = $value;
      }
      else {
        $scalars[$key] = $value;
      }
    }

    return new ArgumentsResolver($scalars, $objects, $wildcards);
  }

}
