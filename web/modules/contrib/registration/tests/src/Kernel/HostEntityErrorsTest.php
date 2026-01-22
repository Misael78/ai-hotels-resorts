<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the errors array for the HostEntity class.
 *
 * @coversDefaultClass \Drupal\registration\HostEntity
 *
 * @group registration
 */
class HostEntityErrorsTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_test_errors',
  ];

  /**
   * @covers ::isEnabledForRegistration
   */
  public function testHostEntityIsEnabledForRegistration() {
    // Node 1: the event subscriber does not make changes.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $errors = [];
    $this->assertTrue($host_entity->isEnabledForRegistration(1, NULL, $errors));
    $this->assertEmpty($errors);

    // Too many spaces.
    $this->assertFalse($host_entity->isEnabledForRegistration(3, NULL, $errors));
    $this->assertArrayHasKey('maximum_spaces', $errors);
    $this->assertCount(1, $errors);
    $this->assertEquals('You may not register for more than 2 spaces.', $errors['maximum_spaces']);

    $settings->set('maximum_spaces', 1);
    $settings->save();
    $this->assertFalse($host_entity->isEnabledForRegistration(2, NULL, $errors));
    $this->assertArrayHasKey('maximum_spaces', $errors);
    $this->assertCount(1, $errors);
    $this->assertEquals('You may not register for more than 1 space.', $errors['maximum_spaces']);

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room.
    $errors = [];
    $this->assertFalse($host_entity->isEnabledForRegistration(1, NULL, $errors));
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('capacity', $errors);
    $this->assertEquals('Sorry, unable to register for %label due to: insufficient spaces remaining.', $errors['capacity']->getUntranslatedString());

    // Node 2: the event subscriber adds an error.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $errors = [];
    $this->assertFalse($host_entity->isEnabledForRegistration(1, NULL, $errors));
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room and too many spaces.
    $errors = [];
    $this->assertFalse($host_entity->isEnabledForRegistration(7, NULL, $errors));
    $this->assertArrayHasKey('capacity', $errors);
    $this->assertArrayHasKey('maximum_spaces', $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertCount(3, $errors);
    $this->assertEquals('Sorry, unable to register for %label due to: insufficient spaces remaining.', $errors['capacity']->getUntranslatedString());
    $this->assertEquals('You may not register for more than 5 spaces.', $errors['maximum_spaces']);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Node 3: the event subscriber adds one error and removes another.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $errors = [];
    $this->assertFalse($host_entity->isEnabledForRegistration(1, NULL, $errors));
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room and too many spaces.
    $errors = [];
    $this->assertFalse($host_entity->isEnabledForRegistration(7, NULL, $errors));
    $this->assertArrayHasKey('maximum_spaces', $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertCount(2, $errors);
    $this->assertEquals('You may not register for more than 5 spaces.', $errors['maximum_spaces']);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Node 4: the event subscriber removes an error.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room.
    $errors = [];
    $this->assertTrue($host_entity->isEnabledForRegistration(1, NULL, $errors));
    $this->assertEmpty($errors);

    // Before open.
    $this->assertFalse($host_entity->isBeforeOpen());
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isBeforeOpen());

    $errors = [];
    $this->assertFalse($host_entity->isEnabledForRegistration(1, NULL, $errors));
    $this->assertArrayHasKey('open', $errors);
    $this->assertCount(1, $errors);
    $this->assertEquals('Registration for %label is not open yet.', $errors['open']->getUntranslatedString());

    // After close.
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isAfterClose());

    $errors = [];
    $this->assertFalse($host_entity->isEnabledForRegistration(1, NULL, $errors));
    $this->assertArrayHasKey('open', $errors);
    $this->assertArrayHasKey('close', $errors);
    $this->assertCount(2, $errors);
    $this->assertEquals('Registration for %label is not open yet.', $errors['open']->getUntranslatedString());
    $this->assertEquals('Registration for %label is closed.', $errors['close']->getUntranslatedString());

    // Disable registration.
    $settings->set('status', FALSE);
    $settings->save();
    $errors = [];
    $this->assertFalse($host_entity->isEnabledForRegistration(1, NULL, $errors));
    $this->assertArrayHasKey('open', $errors);
    $this->assertArrayHasKey('close', $errors);
    $this->assertArrayHasKey('status', $errors);
    $this->assertCount(3, $errors);
    $this->assertEquals('Registration for %label is not open yet.', $errors['open']->getUntranslatedString());
    $this->assertEquals('Registration for %label is closed.', $errors['close']->getUntranslatedString());
    $this->assertEquals('Registration for %label is disabled.', $errors['status']->getUntranslatedString());

    // Disable registration as the only error.
    $settings->set('open', NULL);
    $settings->set('close', NULL);
    $settings->save();
    $errors = [];
    $this->assertFalse($host_entity->isEnabledForRegistration(1, NULL, $errors));
    $this->assertArrayHasKey('status', $errors);
    $this->assertCount(1, $errors);
    $this->assertEquals('Registration for %label is disabled.', $errors['status']->getUntranslatedString());
  }

  /**
   * @covers ::IsAvailableForRegistration
   */
  public function testHostEntityIsAvailableForRegistration() {
    // Node 1: the event subscriber does not make changes.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertEmpty($errors);

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room.
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('capacity', $errors);
    $this->assertEquals('Sorry, unable to register for %label due to: insufficient spaces remaining.', $errors['capacity']->getUntranslatedString());

    // Node 2: the event subscriber adds an error.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room.
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(2, $errors);
    $this->assertArrayHasKey('capacity', $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('Sorry, unable to register for %label due to: insufficient spaces remaining.', $errors['capacity']->getUntranslatedString());
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Node 3: the event subscriber adds one error and removes another.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room.
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Node 4: the event subscriber removes an error.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room.
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertEmpty($errors);

    // Before open.
    $this->assertFalse($host_entity->isBeforeOpen());
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isBeforeOpen());

    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('open', $errors);
    $this->assertEquals('Registration for %label is not open yet.', $errors['open']->getUntranslatedString());

    // After close.
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isAfterClose());

    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(2, $errors);
    $this->assertArrayHasKey('open', $errors);
    $this->assertArrayHasKey('close', $errors);
    $this->assertEquals('Registration for %label is not open yet.', $errors['open']->getUntranslatedString());
    $this->assertEquals('Registration for %label is closed.', $errors['close']->getUntranslatedString());

    // Disable registration.
    $settings->set('status', FALSE);
    $settings->save();
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertArrayHasKey('open', $errors);
    $this->assertArrayHasKey('close', $errors);
    $this->assertArrayHasKey('status', $errors);
    $this->assertCount(3, $errors);
    $this->assertEquals('Registration for %label is not open yet.', $errors['open']->getUntranslatedString());
    $this->assertEquals('Registration for %label is closed.', $errors['close']->getUntranslatedString());
    $this->assertEquals('Registration for %label is disabled.', $errors['status']->getUntranslatedString());

    // Disable registration as the only error.
    $settings->set('open', NULL);
    $settings->set('close', NULL);
    $settings->save();
    $validation_result = $host_entity->IsAvailableForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertEquals('Registration for %label is disabled.', $errors['status']->getUntranslatedString());
  }

  /**
   * @covers ::IsOpenForRegistration
   */
  public function testHostEntityIsOpenForRegistration() {
    // Node 1: the event subscriber does not make changes.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertEmpty($errors);

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room, but still open.
    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertEmpty($errors);

    // Node 2: the event subscriber adds an error.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room, but still open.
    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Node 3: the event subscriber adds one error and removes another.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room, but still open.
    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('special_field', $errors);
    $this->assertEquals('A special field has an error.', $errors['special_field']);

    // Node 4: the event subscriber removes an error.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();

    // Use up capacity.
    $settings->set('maximum_spaces', 5);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->set('count', 5);
    $registration->save();

    // Out of room, but still open.
    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertTrue($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertEmpty($errors);

    // Before open.
    $this->assertFalse($host_entity->isBeforeOpen());
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isBeforeOpen());

    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('open', $errors);
    $this->assertEquals('Registration for %label is not open yet.', $errors['open']->getUntranslatedString());

    // After close.
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isAfterClose());

    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(2, $errors);
    $this->assertArrayHasKey('open', $errors);
    $this->assertArrayHasKey('close', $errors);
    $this->assertEquals('Registration for %label is not open yet.', $errors['open']->getUntranslatedString());
    $this->assertEquals('Registration for %label is closed.', $errors['close']->getUntranslatedString());

    // Disable registration.
    $settings->set('status', FALSE);
    $settings->save();
    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertArrayHasKey('open', $errors);
    $this->assertArrayHasKey('close', $errors);
    $this->assertArrayHasKey('status', $errors);
    $this->assertCount(3, $errors);
    $this->assertEquals('Registration for %label is not open yet.', $errors['open']->getUntranslatedString());
    $this->assertEquals('Registration for %label is closed.', $errors['close']->getUntranslatedString());
    $this->assertEquals('Registration for %label is disabled.', $errors['status']->getUntranslatedString());

    // Disable registration as the only error.
    $settings->set('open', NULL);
    $settings->set('close', NULL);
    $settings->save();
    $validation_result = $host_entity->IsOpenForRegistration(TRUE);
    $this->assertFalse($validation_result->isValid());
    $errors = $validation_result->getLegacyErrors();
    $this->assertCount(1, $errors);
    $this->assertEquals('Registration for %label is disabled.', $errors['status']->getUntranslatedString());
  }

}
