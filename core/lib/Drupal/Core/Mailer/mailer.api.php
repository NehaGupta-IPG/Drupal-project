<?php

/**
 * @file
 * Hooks and documentation related to the experimental mailer.
 */

use Drupal\Core\Mailer\EmailControllerParams;
use Symfony\Component\Mime\Email;

/**
 * @defgroup mailer Experimental Mailer
 * @{
 * Define content, behavior and appearance of transactional email.
 *
 * @section sec_overview Overview and terminology
 *
 * There are two activities when it comes to send transactional mail from
 * Drupal:
 *
 * - Building mails
 * - Delivering mails
 *
 * The experimental mailer delegates mail delivery to the Symfony Mailer
 * component and provides a convenient toolkit based on well known Drupal
 * patterns for the mail building activity.
 *
 * The following sections of this topic provide an overview of the mailer API.
 * For more detailed information, see
 * https://www.drupal.org/developing/api/8/mailer
 *
 * @section sec_register Registering emails
 * To register an email, add lines similar to this to a module_name.email.yml
 * file in your top-level module directory:
 * @code
 * user.password_reset:
 *   label: 'Password recovery'
 *   module: 'user'
 *   key: 'password_reset'
 *   defaults:
 *     _controller: 'Drupal\mailer\MailHandler\UserNotificationHandler:build'
 * @endcode
 * Some notes:
 * - The first line is the machine name of the email. Typically, it is prefixed
 *   by the machine name of the module that defines the email.
 * - The 'label' property contains the human-readable name of the email.
 * - The 'module' specifies the email sending module. Defaults to the module
 *   which registers the email.
 * - The 'key' is a machine name used to identify different emails sent from a
 *   module.
 * - The 'defaults' section tells how to build the main content of the email,
 *   and can also give other information, such as additional arguments for the
 *   email controller method. There are several possibilities for how to build
 *   the main content, including:
 *   - _controller: A callable, usually a method on a controller class (see
 *     @ref sec_controller below for details).
 *   When no controller is defined, the email arguments will be passed to the
 *   email render element and consequently to the associated twig template
 *   directly.
 *
 * Email definitions registered by other modules can be altered using
 * hook_email_info_alter().
 *
 * @todo Derivatives will probably be defined for these; when they are, add
 *   documentation here.
 *
 * @section sec_controller Email controllers
 * After you have defined the email in a *.email.yml file (see @ref sec_register
 * above), the next step is to define an email controller class and method.
 * Email controller classes do not necessarily need to implement any particular
 * interface or extend any particular base class. The only requirement is that
 * the method specified in your *.email.yml file returns:
 * - A render array or an instance of Drupal\Core\Render\RenderableInterface
 *   (see the @link theme_render Theme and render topic @endlink for more
 *   information). This render array is then rendered into the HTML body of an
 *   email message.
 *
 * If the mail sending module may add parameters as key-value pairs. Those will
 * be passed to the method (using reflection) by name.
 *
 * Some parameters are always passed to the controller, if a matching argument
 * is found in the method signature.
 *
 * - string $id: The email id.
 * - string $module: The email sending module.
 * - string $key: The email key used to identify different emails sent from a
 *   module.
 * - string $langcode: The email language code.
 *
 * Additionally, if a parameter is typed to one of the following special classes
 * the system will pass those values as well.
 *
 * - \Symfony\Component\Mime\Email: The Symfony email object. It is generally
 *   useful to set the subject and add additional headers. By convention, this
 *   parameter is usually named $email.
 * - Drupal\Core\Mailer\EmailControllerParams: All of the email controller
 *   parameters in one object. This is useful for generic controllers which
 *   are dispatching the rendering into their own subsystems. By convention,
 *   this parameter is usually named $params.
 *
 * Some controllers will need to display some information stored in the Drupal
 * database, which will involve using one or more Drupal services (see the
 * @link container Services and container topic @endlink). In order to properly
 * inject services, a controller should implement
 * \Drupal\Core\DependencyInjection\ContainerInjectionInterface; controllers can
 * do this by using the
 * \Drupal\Core\DependencyInjection\AutowireTrait trait.
 *
 * @}
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter all the email definitions discovered by the email plugin manager.
 *
 * @param array<string,\Drupal\Core\Mailer\EmailDefinition> &$definitions
 *   The discovered email definitions to be altered. Each definition has a key
 *   that is the machine name, which must be unique.
 *
 *   The value corresponding to each machine name key is an EmailDefinition
 *   value object.
 *
 * @see \Drupal\Core\Mailer\EmailDefinition
 *
 * @ingroup mailer
 */
function hook_email_info_alter(array &$definitions): void {
}

/**
 * Alter the parameters before they are passed to the email controller.
 *
 * @param \Drupal\Core\Mailer\EmailControllerParams $params
 *   A value object representing the controller params.
 *
 * @see \Drupal\Core\Mailer\EmailControllerParams
 *
 * @ingroup mailer
 */
function hook_email_params_alter(EmailControllerParams $params): void {
}

/**
 * Alter the render array generated by the controller.
 *
 * @param array $build
 *   The renderable array generated by the controller.
 * @param array $context
 *   An associative array containing all the arguments available to the
 *   controller. At the minimum:
 *   - id: The email id.
 *   - module: The email sending module.
 *   - key: The email key used to identify different emails sent from a module.
 *   - langcode: The email language code.
 *   - params: The controller params object.
 *   - email: The Symfony email object.
 *   Many emails will provide more context. E.g., the user emails typically
 *   provide the user Account in the 'account' context key.
 *
 * @ingroup mailer
 */
function hook_email_build_alter(array &$build, array $context): void {
}

/**
 * Alter the rendered Symfony email object.
 *
 * @param \Symfony\Component\Mime\Email $email
 *   The Symfony email object.
 * @param array $context
 *   An associative array containing all the arguments available to the
 *   controller. At the minimum:
 *   - id: The email id.
 *   - module: The email sending module.
 *   - key: The email key used to identify different emails sent from a module.
 *   - langcode: The email language code.
 *   - params: The controller params object.
 *   - metadata: The bubbleable metadata generated during the rendering.
 *   Many emails will provide more context. E.g., the user emails typically
 *   provide the user Account in the 'account' context key.
 *
 * @ingroup mailer
 */
function hook_email_alter(Email $email, array $context): void {
}

/**
 * @} End of "addtogroup hooks".
 */
