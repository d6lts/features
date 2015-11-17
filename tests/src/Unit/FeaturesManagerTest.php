<?php

/**
 * @file
 * Contains \Drupal\Tests\features\Unit\FeaturesManagerTest.
 */

namespace Drupal\Tests\features\Unit;

use Drupal\features\FeaturesAssignerInterface;
use Drupal\features\FeaturesBundle;
use Drupal\features\FeaturesBundleInterface;
use Drupal\features\FeaturesManager;
use Drupal\features\FeaturesManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass Drupal\features\FeaturesManager
 * @group features
 */
class FeaturesManagerTest extends UnitTestCase {

  /**
   * @var \Drupal\features\FeaturesManagerInterface
   */
  protected $featuresManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $entity_type = $this->getMock('\Drupal\Core\Config\Entity\ConfigEntityTypeInterface');
    $entity_type->expects($this->any())
      ->method('getConfigPrefix')
      ->willReturn('custom');
    $entity_manager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');
    $entity_manager->expects($this->any())
      ->method('getDefinition')
      ->willReturn($entity_type);
    $config_factory = $this->getMock('\Drupal\Core\Config\ConfigFactoryInterface');
    $storage = $this->getMock('Drupal\Core\Config\StorageInterface');
    $config_manager = $this->getMock('Drupal\Core\Config\ConfigManagerInterface');
    $module_handler = $this->getMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $this->featuresManager = new FeaturesManager($entity_manager, $config_factory, $storage, $config_manager, $module_handler);
  }

  /**
   * @covers ::getActiveStorage
   */
  public function testGetActiveStorage() {
    $this->assertInstanceOf('\Drupal\Core\Config\StorageInterface', $this->featuresManager->getActiveStorage());
  }

  /**
   * @covers ::getExtensionStorages
   */
  public function testGetExtensionStorages() {
    $this->assertInstanceOf('\Drupal\features\FeaturesExtensionStoragesInterface', $this->featuresManager->getExtensionStorages());
  }

  /**
   * @covers ::getFullName
   * @dataProvider providerTestGetFullName
   */
  public function testGetFullName($type, $name, $expected) {
    $this->assertEquals($this->featuresManager->getFullName($type, $name), $expected);
  }

  /**
   * Data provider for ::testGetFullName().
   */
  public function providerTestGetFullName() {
    return [
      [NULL, 'name', 'name'],
      [FeaturesManagerInterface::SYSTEM_SIMPLE_CONFIG, 'name', 'name'],
      ['custom', 'name', 'custom.name'],
    ];
  }

  /**
   * @covers ::getPackage
   * @covers ::getPackages
   * @covers ::reset
   * @covers ::setPackages
   */
  public function testPackages() {
    $packages = ['foo' => 'bar'];
    $this->featuresManager->setPackages($packages);
    $this->assertEquals($packages, $this->featuresManager->getPackages());
    $this->assertEquals('bar', $this->featuresManager->getPackage('foo'));
    $this->featuresManager->reset();
    $this->assertArrayEquals([], $this->featuresManager->getPackages());
    $this->assertNull($this->featuresManager->getPackage('foo'));
  }

  /**
   * @covers ::setConfigCollection
   * @covers ::getConfigCollection
   */
  public function testConfigCollection() {
    $config = ['config' => 'collection'];
    $this->featuresManager->setConfigCollection($config);
    $this->assertArrayEquals($config, $this->featuresManager->getConfigCollection());
  }

  /**
   * @covers ::setPackage
   */
  public function testSetPackage() {
    // @todo
  }

  protected function getAssignInterPackageDependenciesConfigCollection() {
    $config_collection = [];
    $config_collection['example.config'] = [
      'name' => 'example.config',
      'data' => [
        'dependencies' => [
          'config' => [
            'example.config2',
            'example.config3',
          ],
        ],
      ],
      'package' => 'package',
    ];
    $config_collection['example.config2'] = [
      'name' => 'example.config2',
      'data' => [
        'dependencies' => [],
      ],
      'package' => 'package2',
      'providing_feature' => 'my_feature',
    ];
    $config_collection['example.config3'] = [
      'name' => 'example.config3',
      'data' => [
        'dependencies' => [],
      ],
      'package' => '',
      'providing_feature' => 'my_other_feature',
    ];
    return $config_collection;
  }

  /**
   * @covers ::assignInterPackageDependencies
   */
  public function testAssignInterPackageDependenciesWithoutBundle() {
    $assigner = $this->prophesize(FeaturesAssignerInterface::class);
    $bundle = $this->prophesize(FeaturesBundleInterface::class);
    // Provide a bundle without any prefix.
    $bundle->getFullName('package')->willReturn('package');
    $bundle->getFullName('package2')->willReturn('package2');
    $assigner->getBundle('')->willReturn($bundle->reveal());
    $this->featuresManager->setAssigner($assigner->reveal());

    $this->featuresManager->setConfigCollection($this->getAssignInterPackageDependenciesConfigCollection());

    $packages = [
      'package' => [
        'machine_name' => 'package',
        'config' => ['example.config', 'example.config3'],
        'dependencies' => [],
        'bundle' => '',
      ],
      'package2' => [
        'machine_name' => 'package2',
        'config' => ['example.config2'],
        'dependencies' => [],
        'bundle' => '',
      ],
    ];

    $expected = $packages;
    // example.config3 has a providing_feature but no assigned package.
    $expected['package']['dependencies'][] = 'my_other_feature';
    // my_package2 provides configuration required by configuration in
    // my_package.
    // Because package assignments take precedence over providing_feature ones,
    // package2 should have been assigned rather than my_feature.
    $expected['package']['dependencies'][] = 'package2';
    $this->featuresManager->setPackages($packages);

    $this->featuresManager->assignInterPackageDependencies($packages);
    $this->assertEquals($expected, $packages);
  }

  /**
   * @covers ::assignInterPackageDependencies
   */
  public function testAssignInterPackageDependenciesWithBundle() {
    $assigner = $this->prophesize(FeaturesAssignerInterface::class);
    $bundle = $this->prophesize(FeaturesBundleInterface::class);
    // Provide a bundle without any prefix.
    $bundle->getFullName('package')->willReturn('package');
    $bundle->getFullName('package2')->willReturn('package2');
    $assigner->getBundle('giraffe')->willReturn($bundle->reveal());
    $this->featuresManager->setAssigner($assigner->reveal());

    $this->featuresManager->setConfigCollection($this->getAssignInterPackageDependenciesConfigCollection());

    $packages = [
      'package' => [
        'machine_name' => 'package',
        'config' => ['example.config', 'example.config3'],
        'dependencies' => [],
        'bundle' => 'giraffe',
      ],
      'package2' => [
        'machine_name' => 'package2',
        'config' => ['example.config2'],
        'dependencies' => [],
        'bundle' => 'giraffe',
      ],
    ];

    $expected = $packages;
    // example.config3 has a providing_feature but no assigned package.
    $expected['package']['dependencies'][] = 'my_other_feature';
    // my_package2 provides configuration required by configuration in
    // my_package.
    // Because package assignments take precedence over providing_feature ones,
    // package2 should have been assigned rather than my_feature.
    $expected['package']['dependencies'][] = 'package2';
    $this->featuresManager->setPackages($packages);

    $this->featuresManager->assignInterPackageDependencies($packages);
    $this->assertEquals($expected, $packages);
  }

}
