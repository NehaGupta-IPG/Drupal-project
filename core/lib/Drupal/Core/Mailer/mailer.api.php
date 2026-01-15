<?php

/**
 * @file
 * Hooks and documentation related to the experimental mailer.
 */

use Drupal\Core\Mailer\EmailParams;
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
 *   class: 'Drupal\mailer\Email\UserNotificationEmail'
 * @endcode
 * Some notes:
 * - The first line is the machine name of the email. Typically, it is prefixed
 *   by the machine name of the module that defines the email.
 * - The 'label' property contains the human-readable name of the email.
 * - The 'module' specifies the email sending module. Defaults to the module
 *   which registers the email.
 * - The 'key' is a machine name used to identify different emails sent from a
 *   module.
 * - The 'class' is a fully qualified class name to the email plugin. When no
 *   class is defined, the Drupal\Core\Mailer\DefaultEmail plugin will be used.
 *
 * Email definitions registered by other modules can be altered using
 * hook_email_info_alter().
 *
 * @todo Derivatives will probably be defined for these; when they are, add
 *   documentation here.
 *
 * @section sec_plugin Email plugins
 * After you have defined the email in a *.email.yml file (see @ref sec_register
 * above), the next step is to implement an email plugin class. In most cases,
 * custom email plugin classes should derive from Drupal\Core\Mailer\EmailBase.
 * The plugin base class provides a mechanism to declare methods for the subject
 * and the body with a flexible signature.
 *
 * The mail sending module may add parameters as key-value pairs. Those will be
 * passed to email plugin methods (using reflection) by name.
 *
 * Some parameters are always passed to email methods, if a matching argument
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
 * - Drupal\Core\Mailer\EmailParams: All of the email controller
 *   parameters in one object. This is useful for generic controllers which
 *   are dispatching the rendering into their own subsystems. By convention,
 *   this parameter is usually named $params.
 *
 * Use the #[EmailSubject] attribute to specify a subject method in an email
 * plugin implementation. The method may take any parameters (see above) and
 * should return string|\Stringable|null.
 *
 * Use the #[EmailBody] attribute to specify a body method in an email plugin
 * implementation. The method may take any parameters (see above) and should
 * return a render array or an instance of
 * Drupal\Core\Render\RenderableInterface.
 *
 * Some plugins will need to display some information stored in the Drupal
 * database, which will involve using one or more Drupal services (see the
 * @link container Services and container topic @endlink). In order to properly
 * inject services, a plugin should implement
 * \Drupal\Core\Plugin\ContainerFactoryPluginInterface.
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
 * Alter the parameters before they are passed to the email plugin.
 *
 * @param \Drupal\Core\Mailer\EmailParams $params
 *   A value object representing the plugin params.
 *
 * @see \Drupal\Core\Mailer\EmailParams
 *
 * @ingroup mailer
 */
function hook_email_params_alter(EmailParams $params): void {
}

/**
 * Alter the render array generated by the email plugin.
 *
 * @param array $build
 *   The renderable array generated by the email plugin.
 * @param array $context
 *   An associative array containing all the arguments available to the email
 *   plugin. At the minimum:
 *   - id: The email id.
 *   - module: The email sending module.
 *   - key: The email key used to identify different emails sent from a module.
 *   - langcode: The email language code.
 *   - params: The plugin params object.
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
 *   An associative array containing all the arguments available to the email
 *   plugin. At the minimum:
 *   - id: The email id.
 *   - module: The email sending module.
 *   - key: The email key used to identify different emails sent from a module.
 *   - langcode: The email language code.
 *   - params: The plugin params object.
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
