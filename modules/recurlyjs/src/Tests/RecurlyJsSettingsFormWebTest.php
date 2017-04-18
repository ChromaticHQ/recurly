<?php

namespace Drupal\recurlyjs\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests recurly settings form.
 *
 * @group recurly
 */
class RecurlyJsSettingsFormWebTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['recurlyjs'];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

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
  protected function testSettingsSections() {
    $this->drupalGet('/admin/config/services/recurly');
    $this->assertResponse(200);

    $this->assertText(t('Recurly.js settings'));
  }

  /**
   * Test for the presence of the settings fields.
   */
  protected function testSettingsFields() {
    $this->drupalGet('/admin/config/services/recurly');
    $this->assertField('recurlyjs_enable_add_ons');
    $this->assertField('recurlyjs_enable_coupons');
    $this->assertField('recurlyjs_accept_paypal');
  }

}
