<?php

namespace Drupal\Tests\registration\Functional;

/**
 * Tests registration settings.
 *
 * @group registration
 */
class RegistrationSettingsTest extends RegistrationBrowserTestBase {

  /**
   * Tests registration settings.
   */
  public function testRegistrationSettings() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    // Registration settings can be edited and saved.
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('The settings have been saved.');

    // Registration settings cannot be deleted.
    $this->drupalGet('user/' . $user->id() . '/registrations/settings/delete');
    $this->assertSession()->statusCodeEquals(404);

    // Registration settings cannot be edited if the host entity is not
    // configured for registration.
    $user->set('field_registration', NULL);
    $user->save();
    $this->drupalGet('user/' . $user->id() . '/registrations/settings');
    $this->assertSession()->statusCodeEquals(403);
  }

}
