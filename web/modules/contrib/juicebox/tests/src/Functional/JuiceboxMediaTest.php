<?php

namespace Drupal\Tests\juicebox\Functional;

/**
 * Tests integration with Media module.
 *
 * @group juicebox
 */
class JuiceboxMediaTest extends JuiceboxCaseTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'field_ui',
    'juicebox',
    'juicebox_test_media',
  ];

  /**
   * Asserts that the field formatter is only available for appropriate types.
   */
  public function testMediaIntegration() {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $session = $this->getSession();

    // Juicebox should be an option.
    $this->drupalGet('admin/structure/types/manage/juicebox_image_gallery/display');
    $formatter_options = $session->getPage()->find('css', '[name="fields[field_images][type]"]');
    static::assertNotNull($formatter_options->find('css', 'option[value="juicebox_formatter"]'));

    // Allow YouTube videos in the field as well.
    $this->drupalGet('/admin/structure/types/manage/juicebox_image_gallery/fields/node.juicebox_image_gallery.field_images');

    $session->getPage()->find('css', '[name="settings[handler_settings][target_bundles][remote_video]"]')->check();
    $this->submitForm([], 'Save settings');

    // Juicebox should no longer be an option.
    $this->drupalGet('/admin/structure/types/manage/juicebox_image_gallery/display');
    $formatter_options = $session->getPage()->find('css', '[name="fields[field_images][type]"]');
    static::assertNull($formatter_options->find('css', 'option[value="juicebox_formatter"]'));
  }

}
