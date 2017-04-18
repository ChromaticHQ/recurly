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

    $this->assertText(t('Push notification settings'));
    $this->assertText(t('Built-in subscription/invoice pages'));
  }

  /**
   * Test for the presence of the settings fields.
   */
  protected function testSettingsFields() {
    $this->drupalGet('/admin/config/services/recurly');

    // Account settings group.
    $this->assertText(t('Default account settings'));
    $this->assertField('recurly_private_api_key');
    $this->assertField('recurly_public_key');
    $this->assertField('recurly_subdomain');
    $this->assertField('recurly_default_currency');

    // Push notifcation group.
    $this->assertText(t('Push notification settings'));
    $this->assertField('recurly_listener_key');
    $this->assertField('recurly_push_logging');

    // Account sync group.
    $this->assertField('recurly_entity_type');

    // Buil-in page group.
    $this->assertField('recurly_pages');
    $this->assertField('recurly_coupon_page');
    $this->assertField('recurly_subscription_display');
    $this->assertField('recurly_subscription_max');
    // $this->assertField('recurly_subscription_upgrade_timeframe');
    // $this->assertField('recurly_subscription_downgrade_timeframe');.
    $this->assertField('recurly_subscription_cancel_behavior');
  }

}
