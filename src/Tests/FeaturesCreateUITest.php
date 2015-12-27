<?php

/**
 * @file
 * Contains \Drupal\features\Tests\FeaturesCreateUITest.
 */

namespace Drupal\features\Tests;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the creation of a feature.
 *
 * @group features
 */
class FeaturesCreateUITest extends WebTestBase {

  /**
   * @todo Remove the disabled strict config schema checking.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['features', 'features_ui'];

  /**
   * Tests creating a feature via UI and download it.
   */
  public function testCreateFeaturesUI() {
    $admin_user = $this->createUser(['administer site configuration', 'export configuration', 'administer modules']);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/development/configuration/features');

    $edit = [
      'bundle' => 'default',
    ];
    $this->drupalPostForm(NULL, $edit, t('Create new feature'));
    $this->assertResponse(200);

    $edit = [
      'name' => 'Test feature',
      'machine_name' => 'test_feature',
      'description' => 'Test description: <strong>giraffe</strong>',
      'version' => '8.x-1.0',
      'system_simple[sources][selected][system.theme]' => TRUE,
      'system_simple[sources][selected][user.settings]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, t('Download Archive'));

    $this->assertResponse(200);
    $archive = $this->getRawContent();
    $filename = tempnam($this->tempFilesDirectory, 'feature');
    file_put_contents($filename, $archive);

    $archive = new ArchiveTar($filename);
    $files = $archive->listContent();

    $this->assertEqual(3, count($files));
    $this->assertEqual('test_feature/test_feature.info.yml', $files[0]['filename']);
    $this->assertEqual('test_feature/config/install/system.theme.yml', $files[1]['filename']);
    $this->assertEqual('test_feature/config/install/user.settings.yml', $files[2]['filename']);

    // Ensure that the archive contains the expected values.
    $info_filename = tempnam($this->tempFilesDirectory, 'feature');
    file_put_contents($info_filename, $archive->extractInString('test_feature/test_feature.info.yml'));
    /** @var \Drupal\Core\Extension\InfoParser $info_parser */
    $info_parser = \Drupal::service('info_parser');
    $parsed_info = $info_parser->parse($info_filename);
    $this->assertEqual('Test feature', $parsed_info['name']);
    $this->assertEqual([
      'required' => ['system.theme', 'user.settings'],
    ], $parsed_info['features']);

    $archive->extract(\Drupal::service('kernel')->getSitePath() . '/modules');
    $module_path = \Drupal::service('kernel')->getSitePath() . '/modules/test_feature';

    // Ensure that the features listing renders the right content.
    $this->drupalGet('admin/config/development/configuration/features');
    $tr = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "test_feature"]')[0];
    $this->assertLink('Test feature');
    $this->assertEqual('test_feature', (string) $tr->children()[2]);
    $description_column = (string) $tr->children()[3]->asXml();
    $this->assertTrue(strpos($description_column, 'system.theme') !== FALSE);
    $this->assertTrue(strpos($description_column, 'user.settings') !== FALSE);
    $this->assertRaw('Test description: <strong>giraffe</strong>');
    $this->assertEqual('Uninstalled', (string) $tr->children()[5]);
    $this->assertEqual('', (string) $tr->children()[6]);

    // Remove one and add new configuration.
    $this->clickLink('Test feature');
    $edit = [
      'system_simple[included][system.theme]' => FALSE,
      'user_role[sources][selected][authenticated]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, t('Write'));
    $info_filename = $module_path . '/test_feature.info.yml';

    $parsed_info = $info_parser->parse($info_filename);
    $this->assertEqual('Test feature', $parsed_info['name']);
    $this->assertEqual([
      'required' => ['user.settings', 'user.role.authenticated'],
    ], $parsed_info['features']);

    $this->drupalGet('admin/modules');
    $edit = [
      'modules[Other][test_feature][enable]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, t('Install'));

    // Check that the feature is listed as installed.
    $this->drupalGet('admin/config/development/configuration/features');

    $tr = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "test_feature"]')[0];
    $this->assertEqual('Enabled', (string) $tr->children()[5]);

    // Check that a config change results in a feature marked as changed.
    \Drupal::configFactory()->getEditable('user.settings')
      ->set('anonymous', 'Anonymous giraffe')
      ->save();

    $this->drupalGet('admin/config/development/configuration/features');

    $tr = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "test_feature"]')[0];
    $this->assertTrue(strpos($tr->children()[6]->asXml(), 'Changed') !== FALSE);

    // Uninstall module.
    $this->drupalPostForm('admin/modules/uninstall', [
      'uninstall[test_feature]' => TRUE,
    ], t('Uninstall'));
    $this->drupalPostForm(NULL, [], t('Uninstall'));

    $this->drupalGet('admin/config/development/configuration/features');

    $tr = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "test_feature"]')[0];
    $this->assertTrue(strpos($tr->children()[6]->asXml(), 'Changed') !== FALSE);

    $this->clickLink(t('Changed'));
    $this->assertRaw('<td class="diff-context diff-deletedline">anonymous : Anonymous <span class="diffchange">giraffe</span></td>');
    $this->assertRaw('<td class="diff-context diff-addedline">anonymous : Anonymous</td>');

    $this->drupalGet('admin/modules');
    $edit = [
      'modules[Other][test_feature][enable]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, t('Install'));
    $this->drupalGet('admin/config/development/configuration/features');
    $tr = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "test_feature"]')[0];
    $this->assertEqual('Enabled', (string) $tr->children()[5]);

    // Ensure that the changed config got overridden.
    $this->assertEqual('Anonymous', \Drupal::config('user.settings')->get('anonymous'));

    // Change the value, export and ensure that its not shown as changed.
    \Drupal::configFactory()->getEditable('user.settings')
      ->set('anonymous', 'Anonymous giraffe')
      ->save();

    // Ensure that exporting this change will result in an unchanged feature.
    $this->drupalGet('admin/config/development/configuration/features');
    $tr = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "test_feature"]')[0];
    $this->assertTrue(strpos($tr->children()[6]->asXml(), 'Changed') !== FALSE);

    $this->clickLink('Test feature');
    $this->drupalPostForm(NULL, [], t('Write'));

    $this->drupalGet('admin/config/development/configuration/features');
    $tr = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "test_feature"]')[0];
    $this->assertEqual('Enabled', (string) $tr->children()[5]);
  }

}
