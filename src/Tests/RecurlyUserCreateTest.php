<?php

namespace Drupal\recurly\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests user creation form with Recurly.
 *
 * @group recurly
 */
class RecurlyUserCreateTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['recurly'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $permissions = [
      'administer users',
      'administer recurly',
    ];
    $this->adminUser = $this->createUser($permissions);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test redirect behavior after creating a user in the admin UI.
   */
  public function testUserAdd() {
    $edit = [
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass[pass1]' => 0,
      'pass[pass2]' => 0,
      'notify' => FALSE,
    ];
    $this->drupalPostForm('admin/people/create', $edit, t('Create new account'));
    $this->assertUrl('admin/people/create', [], t('When Recurly credentials are not yet configured, redirection to the signup page after user creation should not occur.'));
    $this->assertResponse(200);
  }

}
