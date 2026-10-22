<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Template;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Site\Settings;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\Attribute\TwigAllowed;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Core\Template\TwigSandboxPolicy;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Sandbox\SecurityError;

/**
 * Tests the twig sandbox policy.
 */
#[CoversClass(TwigSandboxPolicy::class)]
#[Group('Template')]
class TwigSandboxTest extends UnitTestCase {

  /**
   * The Twig environment loaded with the sandbox extension.
   *
   * @var \Twig\Environment
   */
  protected $twig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $loader = new StringLoader();
    $this->twig = new Environment($loader);
    $policy = new TwigSandboxPolicy();
    $sandbox = new SandboxExtension($policy, TRUE);
    $this->twig->addExtension($sandbox);
  }

  /**
   * Tests that dangerous methods cannot be called in entity objects.
   */
  #[DataProvider('getTwigEntityDangerousMethods')]
  public function testEntityDangerousMethods(string $template): void {
    $this->expectException(SecurityError::class);
    $this->twig->render($template, ['entity' => $this->createStub(EntityInterface::class)]);
  }

  /**
   * Data provider for ::testEntityDangerousMethods.
   *
   * @return array
   *   An array of dangerous methods.
   */
  public static function getTwigEntityDangerousMethods(): array {
    return [
      ['{{ entity.delete }}'],
      ['{{ entity.save }}'],
      ['{{ entity.create }}'],
    ];
  }

  /**
   * Tests that allowed classes can be extended.
   */
  public function testExtendedClass(): void {
    $this->assertEquals(' class=&quot;kitten&quot;', $this->twig->render('{{ attribute.addClass("kitten") }}', ['attribute' => new TestAttribute()]));
  }

  /**
   * Tests that prefixed methods can be called from within Twig templates.
   *
   * Currently "get", "has", and "is" are the only allowed prefixes.
   *
   * @legacy-covers ::__construct
   * @legacy-covers ::checkMethodAllowed
   */
  public function testEntitySafePrefixes(): void {
    $entity = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $entity->expects($this->atLeastOnce())
      ->method('hasLinkTemplate')
      ->with('test')
      ->willReturn(TRUE);
    $result = $this->twig->render('{{ entity.hasLinkTemplate("test") }}', ['entity' => $entity]);
    $this->assertTrue((bool) $result, 'Sandbox policy allows has* functions to be called.');

    $entity = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $entity->expects($this->atLeastOnce())
      ->method('isNew')
      ->willReturn(TRUE);
    $result = $this->twig->render('{{ entity.isNew }}', ['entity' => $entity]);
    $this->assertTrue((bool) $result, 'Sandbox policy allows is* functions to be called.');

    $entity = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $entity->expects($this->atLeastOnce())
      ->method('getEntityType')
      ->willReturn('test');
    $result = $this->twig->render('{{ entity.getEntityType }}', ['entity' => $entity]);
    $this->assertEquals('test', $result, 'Sandbox policy allows get* functions to be called.');
  }

  /**
   * Tests that valid methods can be called from within Twig templates.
   *
   * Currently, the following methods are allowed: id, label, bundle, get.
   *
   * @legacy-covers ::__construct
   * @legacy-covers ::checkMethodAllowed
   */
  public function testEntitySafeMethods(): void {
    $entity = $this->prophesize(EntityInterface::class);
    $entity->id()
      ->shouldBeCalled()
      ->willReturn('1234');
    $result = $this->twig->render('{{ entity.id }}', ['entity' => $entity->reveal()]);
    $this->assertEquals('1234', $result, 'Sandbox policy allows id() to be called.');

    $entity->label()
      ->shouldBeCalled()
      ->willReturn('testing');
    $result = $this->twig->render('{{ entity.label }}', ['entity' => $entity->reveal()]);
    $this->assertEquals('testing', $result, 'Sandbox policy allows label() to be called.');

    $entity->bundle()
      ->shouldBeCalled()
      ->willReturn('testing');
    $result = $this->twig->render('{{ entity.bundle }}', ['entity' => $entity->reveal()]);
    $this->assertEquals('testing', $result, 'Sandbox policy allows bundle() to be called.');
  }

  public static function provideAllowedEntityMethods(): array {
    return [
      ['id'],
      ['bundle'],
      ['label'],
    ];
  }

  /**
   * @legacy-covers ::__construct
   * @legacy-covers ::checkMethodAllowed
   */
  #[DataProvider('provideAllowedEntityMethods')]
  public function testEntitySafeMethodsOnGenerics(string $method): void {
    $sut = new class () {

      public function id(): string {
        return 'id';
      }

      public function label(): string {
        return 'label';
      }

      public function bundle(): string {
        return 'bundle';
      }

    };
    $this->expectException(SecurityError::class);
    $this->twig->render('{{ sut.' . $method . ' }}', ['sut' => $sut]);
  }

  /**
   * Tests that safe methods can be called.
   *
   * @legacy-covers ::__construct
   * @legacy-covers ::checkMethodAllowed
   */
  public function testGloballySafeMethods(): void {
    // Calls to get method.
    $entity = $this->prophesize(ContentEntityBase::class);
    $entity->get('title')
      ->shouldBeCalled()
      ->willReturn('test');
    $result = $this->twig->render('{{ entity.get("title") }}', ['entity' => $entity->reveal()]);
    $this->assertEquals('test', $result, 'Sandbox policy allows get() to be called.');

    $sut = new class () {

      public function get(string $arg): string {
        return $arg;
      }

    };
    $result = $this->twig->render('{{ sut.get("title") }}', ['sut' => $sut]);
    $this->assertEquals('title', $result, 'Sandbox policy allows get() to be called on generic class.');

    // toString methods
    $url = $this->prophesize(Url::class);
    $url->toString()
      ->shouldBeCalledOnce()
      ->willReturn('/are/cute');
    $result = $this->twig->render('{{ url.toString }}', ['url' => $url->reveal()]);
    $this->assertEquals('/are/cute', $result, 'Sandbox policy allows toString() to be called on URL.');

    $sut = new class () {

      public function toString(): string {
        return 'toString';
      }

    };
    $result = $this->twig->render('{{ sut.toString }}', ['sut' => $sut]);
    $this->assertEquals('toString', $result, 'Sandbox policy allows toString() to be called on generic class.');

    $sut = new class () {

      public function __toString() {
        return '__toString';
      }

    };
    $result = $this->twig->render('{{ sut.__toString }}', ['sut' => $sut]);
    $this->assertEquals('__toString', $result, 'Sandbox policy allows __toString() to be called on generic class.');
    $result = $this->twig->render('{{ sut }}', ['sut' => $sut]);
    $this->assertEquals('__toString', $result, 'Sandbox policy allows string conversion on generic class.');
  }

  /**
   * Tests that shared method names across classes works.
   *
   * @legacy-covers ::__construct
   * @legacy-covers ::checkMethodAllowed
   */
  #[RunInSeparateProcess]
  public function testOverrideCollision(): void {
    // Replace settings
    new Settings([
      'twig_sandbox_allowed_methods' => [
        EntityInterface::class . '::id',
        AccountInterface::class . '::id',
      ],
    ]);

    // Rebuild the sandbox policy after the settings change.
    $loader = new StringLoader();
    $this->twig = new Environment($loader);
    $policy = new TwigSandboxPolicy();
    $sandbox = new SandboxExtension($policy, TRUE);
    $this->twig->addExtension($sandbox);

    // Assert entities work.
    $entity = $this->prophesize(EntityInterface::class);
    $entity->id()
      ->shouldBeCalled()
      ->willReturn('1234');
    $result = $this->twig->render('{{ entity.id }}', ['entity' => $entity->reveal()]);
    $this->assertEquals('1234', $result, 'Sandbox policy allows id() to be called.');

    $result = $this->twig->render('{{ entity.id }}', ['entity' => new AnonymousUserSession()]);
    $this->assertEquals('0', $result, 'Sandbox policy allows id() to be called.');
  }

  /**
   * Tests that unqualified twig_sandbox_allowed_methods methods trigger error.
   *
   * @legacy-covers ::__construct
   * @legacy-covers ::checkMethodAllowed
   */
  #[RunInSeparateProcess]
  #[IgnoreDeprecations]
  public function testLegacyAllowedMethod(): void {
    // Replace settings
    new Settings([
      'twig_sandbox_allowed_methods' => [
        'id',
      ],
    ]);
    $this->expectUserDeprecationMessage('Not specifying a fully-qualified method name to twig_sandbox_allowed_methods is deprecated in drupal:11.2.0 and will throw an error in drupal:12.0.0. See https://www.drupal.org/node/3263019');
    new TwigSandboxPolicy();
  }

  /**
   * Tests that method with TwigAllowed attribute is allowed.
   */
  public function testTwigMethodAttributeAllowed(): void {
    $object = new AttributeAllowTestClass();
    $result = $this->twig->render('{{ object.allowed() }}', ['object' => $object]);
    $this->assertTrue((bool) $result, 'TwigAllowed attribute allows method to be called.');
  }

  /**
   * Tests that method without TwigAllowed attribute is not allowed.
   */
  public function testTwigMethodAttributeNotAllowed(): void {
    $object = new AttributeAllowTestClass();
    $this->expectException(SecurityError::class);
    $this->twig->render('{{ object.notAllowed() }}', ['object' => $object]);
  }

}

/**
 * Test class for HTML attributes collector, sanitizer, and renderer.
 */
class TestAttribute extends Attribute {}

/**
 * Test class for TwigAllowed attribute.
 */
class AttributeAllowTestClass {

  #[TwigAllowed]
  public function allowed(): string {
    return __METHOD__;
  }

  public function notAllowed(): string {
    return __METHOD__;
  }

}
