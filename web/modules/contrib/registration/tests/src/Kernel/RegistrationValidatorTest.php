<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\RegistrationValidatorInterface;
use Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint\Constraint1;
use Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint\Constraint3;
use Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint\Constraint4;

/**
 * Tests the RegistrationValidator class.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationValidator
 *
 * @group registration
 */
class RegistrationValidatorTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The registration validator.
   */
  protected RegistrationValidatorInterface $validator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_test_validator',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->validator = $this->container->get('registration.validator');
  }

  /**
   * @covers ::execute
   */
  public function testRegistrationValidator() {
    $node1 = $this->createAndSaveNode();
    $node2 = $this->createAndSaveNode();
    $node3 = $this->createAndSaveNode();

    // Cacheability accumulates across constraints.
    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint1',
      'Constraint2',
    ], [$node1, $node2]);

    $this->assertTrue($validation_result->isValid());
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('node:2', $metadata->getCacheTags());
    $this->assertCount(2, $metadata->getCacheTags());
    $this->assertEmpty($metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Violations accumulate across constraints.
    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint1',
      'Constraint2',
      'Constraint3',
    ], [$node1, $node2, $node3]);
    $this->assertFalse($validation_result->isValid());
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('node:2', $metadata->getCacheTags());
    $this->assertContains('node:3', $metadata->getCacheTags());
    $this->assertCount(3, $metadata->getCacheTags());
    $this->assertEmpty($metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());
    $this->assertEquals(1, $validation_result->getViolations()->count());

    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint1',
      'Constraint2',
      'Constraint3',
      'Constraint4',
    ], [$node1, $node2, $node3]);
    $this->assertFalse($validation_result->isValid());
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('node:2', $metadata->getCacheTags());
    $this->assertContains('node:3', $metadata->getCacheTags());
    $this->assertCount(3, $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertCount(1, $metadata->getCacheContexts());
    $this->assertEquals(0, $metadata->getCacheMaxAge());
    $this->assertEquals(2, $validation_result->getViolations()->count());
    $violations = $validation_result->getViolations();
    $this->assertEquals('random3', $violations[0]->getCode());
    $this->assertTrue($violations[0]->getConstraint() instanceof Constraint3);
    $this->assertEquals('random4', $violations[1]->getCode());
    $this->assertTrue($violations[1]->getConstraint() instanceof Constraint4);
  }

  /**
   * Tests caching.
   */
  public function testRegistrationValidatorCaching() {
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');

    $node = $this->createAndSaveNode();
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $node2 = $this->createAndSaveNode();
    $host_entity2 = $handler->createHostEntity($node2);

    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // A settings change breaks cache, but only for its host.
    $settings->set('maximum_spaces', 1);
    $settings->save();
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // A change to the host entity breaks cache.
    $node->set('title', 'Example event');
    $node->save();
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // A new registration breaks cache.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    $registration->save();
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // Logging in as a different user breaks cache.
    $user = $this->createUser(['create registration']);
    $this->setCurrentUser($user);
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // Validating a different host breaks cache.
    $node2 = $this->createAndSaveNode();
    $handler2 = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity2 = $handler->createHostEntity($node2);
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // The earlier result for host 1 is still cached.
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // A new registration breaks cache, but only for the registration host,
    // not all hosts.
    $registration = $this->createRegistration($node2);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    $registration->save();
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // Any update to a registration breaks cache.
    $registration->set('state', 'complete');
    $registration->save();
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // Deleting a registration breaks cache.
    $registration->delete();
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity2->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // Only basic availability checks are cached.
    $settings->save();
    $validation_result = $host_entity->hasRoomForRegistration(1, TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity->hasRoomForRegistration(1, TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    $registration->save();
    $validation_result = $host_entity->validate($registration);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity->validate($registration);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());

    // Validation results with violations are cached.
    $this->assertFalse($host_entity->isBeforeOpen());
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isBeforeOpen());
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $this->assertFalse($validation_result->wasCached());
    $violations = $validation_result->getViolations();
    $this->assertEquals('Registration for <em class="placeholder">Example event</em> is not open yet.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $this->assertTrue($validation_result->wasCached());
    $violations = $validation_result->getViolations();
    $this->assertEquals('Registration for <em class="placeholder">Example event</em> is not open yet.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());
  }

  /**
   * Tests ending the pipeline early.
   */
  public function testRegistrationValidatorEndPipelineEarly() {
    $node1 = $this->createAndSaveNode();
    $node2 = $this->createAndSaveNode();
    $node3 = $this->createAndSaveNode();
    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint1',
      'Constraint2',
      'Constraint3',
    ], ['not a node', $node2, $node3]);
    $this->assertFalse($validation_result->isValid());
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertNotContains('node:2', $metadata->getCacheTags());
    $this->assertNotContains('node:3', $metadata->getCacheTags());
    $this->assertEquals(1, $validation_result->getViolations()->count());
    $violations = $validation_result->getViolations();
    $this->assertEquals('early', $violations[0]->getCode());
    $this->assertTrue($violations[0]->getConstraint() instanceof Constraint1);
  }

  /**
   * Tests the unmet dependencies exception.
   */
  public function testRegistrationValidatorUnmetDependenciesException() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Constraint2 has unmet dependencies');
    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint2',
      'Constraint3',
      'Constraint4',
    ], []);
  }

}
