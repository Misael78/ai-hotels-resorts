<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\registration\RegistrationValidationResult;

/**
 * Tests the RegistrationValidationResult class.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationValidationResult
 *
 * @group registration
 */
class RegistrationValidationResultTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;

  /**
   * @covers ::addCacheableDependency
   * @covers ::addViolation
   * @covers ::addViolations
   * @covers ::getCacheableMetadata
   * @covers ::getConstraints
   * @covers ::getValue
   * @covers ::getViolations
   * @covers ::hasViolationWithCode
   * @covers ::isValid
   * @covers ::removeAllViolations
   * @covers ::removeViolationWithCode
   */
  public function testRegistrationValidationResult() {
    $node = $this->createAndSaveNode();

    // New results start out valid.
    $validation_result = new RegistrationValidationResult(['ExampleConstraint'], 'example value');
    $this->assertEquals(['ExampleConstraint'], $validation_result->getConstraints());
    $this->assertEquals('example value', $validation_result->getValue());
    $this->assertTrue($validation_result->isValid());

    // Cache dependencies can be added.
    $validation_result->addCacheableDependency($node);
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertEmpty($metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Attempting to add a dependency on a new entity is ignored.
    $node = $this->createNode();
    $this->assertTrue($node->isNew());
    $validation_result->addCacheableDependency($node);
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertNotContains('node:2', $metadata->getCacheTags());
    $this->assertEmpty($metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Cache contexts can be added.
    $access_result = AccessResult::allowed()->cachePerPermissions();
    $validation_result->addCacheableDependency($access_result);
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertNotContains('node:2', $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertNotContains('url', $metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Cache dependencies from a second result can be added.
    $validation_result2 = new RegistrationValidationResult(['ExampleConstraint2'], 'example value2');
    $node = $this->createAndSaveNode();
    $validation_result2->addCacheableDependency($node);
    $access_result = AccessResult::allowed()->addCacheContexts(['url'])->setCacheMaxAge(0);
    $validation_result2->addCacheableDependency($access_result);
    $validation_result->addCacheableDependency($validation_result2);
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('node:2', $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertContains('url', $metadata->getCacheContexts());
    $this->assertEquals(0, $metadata->getCacheMaxAge());

    // Attempting to add a dependency on a new entity is ignored.
    $node = $this->createNode();
    $validation_result = new RegistrationValidationResult(['ExampleConstraint'], 'example value');
    $this->assertTrue($node->isNew());
    $validation_result->addCacheableDependency($node);
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertEmpty($metadata->getCacheTags());
    $this->assertEmpty($metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Attempting to add a dependency on a value that does not support cache
    // dependencies makes the result uncacheable.
    $validation_result->addCacheableDependency('a string');
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertEmpty($metadata->getCacheTags());
    $this->assertEmpty($metadata->getCacheContexts());
    $this->assertEquals(0, $metadata->getCacheMaxAge());

    // Attempting to add a dependency on NULL makes the result uncacheable.
    $validation_result->addCacheableDependency(NULL);
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertEmpty($metadata->getCacheTags());
    $this->assertEmpty($metadata->getCacheContexts());
    $this->assertEquals(0, $metadata->getCacheMaxAge());

    // Violations can be added.
    $validation_result->addViolation('This is an example error message 1.', [], NULL, NULL, NULL, 'example_error_code1');
    $validation_result->addViolation('This is an example error message 2.', [], NULL, NULL, NULL, 'example_error_code2');
    $violations = $validation_result->getViolations();
    $this->assertCount(2, $violations);
    $this->assertEquals('This is an example error message 1.', (string) $violations[0]->getMessage());
    $this->assertEquals('This is an example error message 2.', (string) $violations[1]->getMessage());

    // Messages can be replaced.
    $validation_result->addViolation('This is a different message with the same code.', [], NULL, NULL, NULL, 'example_error_code2');
    $violations = $validation_result->getViolations();
    $this->assertCount(2, $violations);
    $this->assertEquals('This is an example error message 1.', (string) $violations[0]->getMessage());
    // When replacing a message, the offset from the previous message is
    // deleted, leaving a hole at offset 1.
    $this->assertEquals('This is a different message with the same code.', (string) $violations[2]->getMessage());

    // The existence of a violation with a specific code can be checked.
    $this->assertTrue($validation_result->hasViolationWithCode('example_error_code1'));
    $this->assertTrue($validation_result->hasViolationWithCode('example_error_code2'));
    $this->assertFalse($validation_result->hasViolationWithCode('example_error_code3'));

    // Violations can be removed by code.
    $validation_result->removeViolationWithCode('example_error_code1');
    $this->assertFalse($validation_result->hasViolationWithCode('example_error_code1'));
    $this->assertTrue($validation_result->hasViolationWithCode('example_error_code2'));
    $this->assertFalse($validation_result->hasViolationWithCode('example_error_code3'));

    $validation_result->addViolation('This is an example error message 1.', [], NULL, NULL, NULL, 'example_error_code1');
    $validation_result->addViolation('This is an example error message 3.', [], NULL, NULL, NULL, 'example_error_code3');
    $violations = $validation_result->getViolations();
    $this->assertCount(3, $violations);
    // Attempting to remove a violation with a non-existent code has no effect.
    $validation_result->removeViolationWithCode('example_error_code4');
    $violations = $validation_result->getViolations();
    $this->assertCount(3, $violations);
    $this->assertFalse($validation_result->isValid());

    // All violations can be removed at once.
    $validation_result->removeAllViolations();
    $violations = $validation_result->getViolations();
    $this->assertCount(0, $violations);
    $this->assertTrue($validation_result->isValid());
  }

}
