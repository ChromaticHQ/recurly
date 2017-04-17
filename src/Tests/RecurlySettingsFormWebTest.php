<?php

namespace Drupal\recurly\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests recurly settings form.
 *
 * @group recurly
 */
class RecurlySettingsFormWebTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['recurly'];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Form values that are set by default.
   *
   * @var array
   */
  protected $defaultFormValues = [
    'recurly_default_currency' => 'USD',
    'recurly_public_key' => '',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $permissions = [
      'administer recurly',
    ];
    $this->adminUser = $this->createUser($permissions);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test the settings page.
   */
  protected function testSettingsPage() {
    $this->drupalGet('/admin/config/services/recurly');

    $this->assertText(t('Default account settings'));
    $this->assertText(t('Push notification settings'));
    $this->assertText(t('Built-in subscription/invoice pages'));
  }

  /**
   * Test for the presence of the settings fields.
   */
  protected function testSettingsFields() {
    $this->drupalGet('/admin/config/services/recurly');
    $this->assertField('edit-recurly-public-key');
  }

  /**
   * Test if the default values are shown correctly in the form.
   */
  protected function testDefaultFormValues() {
    $this->drupalGet('/admin/config/services/recurly');
  }

}
