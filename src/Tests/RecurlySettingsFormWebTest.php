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

    $this->assertNoDuplicateIds();

    $this->assertText(t('Default account settings'));
    $this->assertText(t('Push notification settings'));
    $this->assertText(t('Built-in subscription/invoice pages'));
  }

  /**
   * Test account settings fields.
   */
  protected function testAccountSettingsFields() {
    $this->drupalGet('/admin/config/services/recurly');
    $this->assertFieldById('edit-recurly-private-api-key');
    $this->assertFieldById('edit-recurly-public-key');
    $this->assertFieldById('edit-recurly-subdomain');
    $this->assertFieldByName('recurly_default_currency');
    $this->assertOptionSelected('edit-recurly-default-currency', 'USD', 'Currency field defaults to USD.');
  }

  /**
   * Test push notification settings fields.
   */
  protected function testPushNotificationSettingFields() {
    $this->drupalGet('/admin/config/services/recurly');
    $this->assertFieldById('edit-recurly-listener-key');
    $this->assertFieldById('edit-recurly-push-logging');
  }

  /**
   * Test account sync settings fields.
   */
  protected function testAccountSyncSettingsFields() {
    $this->drupalGet('/admin/config/services/recurly');
    $this->assertFieldByName('recurly_entity_type');
    $this->assertOptionSelected('edit-recurly-entity-type', 'user', 'Entity type defaults to user.');
    $this->assertFieldById('edit-recurly-token-mapping-email', '[user:mail]', 'Email token field is present and contains default value: [user:mail].');
    $this->assertFieldById('edit-recurly-token-mapping-username', '[user:name]', 'Username token field is present and contains default value: [user:name].');
  }

  /**
   * Test Recurly pages settings fields.
   */
  protected function testRecurlyPagesSettingsFields() {
    $this->drupalGet('/admin/config/services/recurly');
    $this->assertField('recurly_pages');
    $this->assertFieldChecked('edit-recurly-pages', 'Recurly pages enabled by default.');

    $this->assertFieldByName('recurly_coupon_page');
    $this->assertFieldChecked('edit-recurly-coupon-page', 'Recurly coupon pages enabled by default.');

    $this->assertFieldByName('recurly_subscription_display');
    $this->assertFieldById('edit-recurly-subscription-display-live', 'live', 'Recurly list subscriptions \'live\' option present.');
    $this->assertFieldById('edit-recurly-subscription-display-all', 'all', 'Recurly list subscriptions \'all\' option present.');
    $this->assertFieldChecked('edit-recurly-subscription-display-live', 'Recurly subscription display set to \'live\' by default.');

    $this->assertFieldByName('recurly_subscription_max');
    $this->assertFieldById('edit-recurly-subscription-max-1', '1', 'Recurly single plan option present.');
    $this->assertFieldById('edit-recurly-subscription-max-0', '0', 'Recurly multiple plan option present.');
    $this->assertFieldChecked('edit-recurly-subscription-max-1', 'Recurly set to single plan mode by default.');

    $this->assertFieldByName('recurly_subscription_upgrade_timeframe');
    $this->assertFieldById('edit-recurly-subscription-upgrade-timeframe-now', 'now', 'Recurly upgrade plan option \'now\' present.');
    $this->assertFieldById('edit-recurly-subscription-upgrade-timeframe-renewal', 'renewal', 'Recurly upgrade plan option \'renewal\' present.');
    $this->assertFieldChecked('edit-recurly-subscription-upgrade-timeframe-now', 'Recurly upgrade plan behavior defaults to \'now\'.');

    $this->assertFieldByName('recurly_subscription_downgrade_timeframe');
    $this->assertFieldById('edit-recurly-subscription-downgrade-timeframe-now', 'now', 'Recurly downgrade plan option \'now\' present.');
    $this->assertFieldById('edit-recurly-subscription-downgrade-timeframe-renewal', 'renewal', 'Recurly downgrade plan option \'renewal\' present.');
    $this->assertFieldChecked('edit-recurly-subscription-downgrade-timeframe-renewal', 'Recurly downgrade plan behavior defaults to \'renewal\'.');

    $this->assertFieldByName('recurly_subscription_cancel_behavior');
    $this->assertFieldById('edit-recurly-subscription-cancel-behavior-cancel', 'cancel', 'Recurly cancel plan option \'at renewal\' present.');
    $this->assertFieldById('edit-recurly-subscription-cancel-behavior-terminate-prorated', 'terminate_prorated', 'Recurly cancel plan option \'immediately with prorated refund\' present.');
    $this->assertFieldById('edit-recurly-subscription-cancel-behavior-terminate-full', 'terminate_full', 'Recurly cancel plan option \'immediately with full refund\' present.');
    $this->assertFieldChecked('edit-recurly-subscription-cancel-behavior-cancel', 'Recurly cancel behavior defaults to \'cancel\'.');
  }

  /**
   * Test settings form submission.
   */
  protected function testSettingsFormSubmission() {
    $this->drupalGet('/admin/config/services/recurly');
    $this->drupalPostForm(NULL, [], t('Save configuration'));
    $this->assertText('The configuration options have been saved.');
  }

}
