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
    protected readonly EmailManagerInterface $emailManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function render(EmailDefinition $definition, array $params, string $langcode): Email {
    // Add defaults from the plugin definition to the params and let modules
    // alter them.
    $params += $definition->getDefaults();
    $context = [
      'id' => $definition->id(),
      'module' => $definition->getModule(),
      'key' => $definition->getKey(),
      'langcode' => $langcode,
    ];
    $this->moduleHandler->alter('email_params', $params, $context);

    // @todo Replace with execution environment once that is available.
    // @see: https://www.drupal.org/i/3536307
    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($definition, $params, $langcode) {
      // Build render array.
      $pluginConfig = $params[EmailDefinition::PLUGIN_CONFIG_NAME] ?? [];
      $email = $this->emailManager->createEmail($definition->id(), $pluginConfig);
      $build = $this->build($email, $definition, $params, $langcode);

      // Render the result.
      if (is_array($build)) {
        // Let modules alter the render array.
        $context = [
          'id' => $definition->id(),
          'module' => $definition->getModule(),
          'key' => $definition->getKey(),
          'langcode' => $langcode,
          'email' => $email,
        ] + ($build['#props'] ?? []);
        unset($build['#props']);
        $this->moduleHandler->alter('email_build', $build, $context);

        $build['#theme'] = 'email';
        $build['#props'] = $context;
        $email->html((string) $this->renderer->render($build));

        // Let modules alter the resulting email. Also make render metadata
        // available in context (contains #attachments and #cache).
        $context['metadata'] = BubbleableMetadata::createFromRenderArray($build);
        unset($context['email']);
        $this->moduleHandler->alter('email_post_render', $email, $context);
      }

      return $email;
    });
  }

  /**
   * Call the email controller and return its result.
   *
   * @param \Symfony\Component\Mime\Email $email
   *   The email instance.
   * @param \Drupal\Core\Mailer\EmailDefinition $definition
   *   The email plugin definition.
   * @param array $params
   *   Parameters to pass to the controller.
   * @param string $langcode
   *   The langauge code.
   *
   * @return mixed
   *   The result returned from the controller.
   */
  protected function build(Email $email, EmailDefinition $definition, array $params, string $langcode): mixed {
    // Resolve controller and arguments.
    try {
      $callable = $this->callableResolver->getCallableFromDefinition($params[EmailDefinition::CONTROLLER_NAME]);
    }
    catch (\InvalidArgumentException $e) {
      throw new \InvalidArgumentException(sprintf('The controller for Email "%s" is not callable.', $definition->id()), 0, $e);
    }
    $resolver = $this->getArgumentResolver($email, $definition, $params, $langcode);
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
   */
  protected function getArgumentResolver(Email $email, EmailDefinition $definition, array $params, string $langcode): ArgumentsResolver {
    $scalars = [
      'id' => $definition->id(),
      'module' => $definition->getModule(),
      'key' => $definition->getKey(),
      'langcode' => $langcode,
    ];
    $objects = [
      'email' => $email,
    ];
    $wildcards = [];

    foreach ($params as $key => $value) {
      if (!str_starts_with('_', $key)) {
        if (is_scalar($value)) {
          $scalars[$key] = $value;
        }
        else {
          $objects[$key] = $value;
        }
      }
    }

    return new ArgumentsResolver($scalars, $objects, $wildcards);
  }

}
