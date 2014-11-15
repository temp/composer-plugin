<?php

/*
 * This file is part of the Composer Puli Plugin.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Extension\Composer\Tests\RepositoryBuilder;

use Puli\Extension\Composer\RepositoryBuilder\RepositoryBuilder;
use Puli\Filesystem\Resource\LocalDirectoryResource;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $repo;

    /**
     * @var RepositoryBuilder
     */
    private $builder;

    private $package1Root;

    private $package2Root;

    private $package3Root;

    protected function setUp()
    {
        $this->repo = $this->getMock('\Puli\Repository\ManageableRepositoryInterface');
        $this->builder = new RepositoryBuilder();
        $this->package1Root = __DIR__.'/Fixtures/package1';
        $this->package2Root = __DIR__.'/Fixtures/package2';
        $this->package3Root = __DIR__.'/Fixtures/package3';
    }

    public function testIgnorePackageWithoutExtras()
    {
        $this->repo->expects($this->never())
            ->method('add');

        $package = $this->createPackage(array());

        $this->builder->loadPackage($package, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testIgnorePackageWithoutResources()
    {
        $this->repo->expects($this->never())
            ->method('add');

        $package = $this->createPackage(array(
            'extra' => array(
            ),
        ));

        $this->builder->loadPackage($package, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testBuildRepository()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/package/css', new LocalDirectoryResource($this->package1Root.'/assets/css'));

        $package = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/package' => 'resources',
                    '/acme/package/css' => 'assets/css',
                ),
            ),
        ));

        $this->builder->loadPackage($package, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\Filesystem\FilesystemException
     */
    public function testFailIfResourceNotFound()
    {
        $package = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/package' => 'foobar',
                ),
            ),
        ));

        $this->builder->loadPackage($package, $this->package1Root);
    }

    public function testIgnoreResourceOrder()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/package/css', new LocalDirectoryResource($this->package1Root.'/assets/css'));

        $package = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/package/css' => 'assets/css',
                    '/acme/package' => 'resources',
                ),
            ),
        ));

        $this->builder->loadPackage($package, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testExportResourceWithMultipleLocalPaths()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/package', new LocalDirectoryResource($this->package1Root.'/assets'));

        $package = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/package' => array('resources', 'assets'),
                ),
            ),
        ));

        $this->builder->loadPackage($package, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideExistingPackage()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/overridden/css', new LocalDirectoryResource($this->package1Root.'/assets/css'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/acme/overridden/css', new LocalDirectoryResource($this->package2Root.'/css-override'));

        $overridingPackage = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'override',
                    '/acme/overridden/css' => 'css-override',
                ),
                'override' => 'acme/overridden',
            ),
        ));

        $overriddenPackage = $this->createPackage(array(
            'name' => 'acme/overridden',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'resources',
                    '/acme/overridden/css' => 'assets/css',
                ),
            ),
        ));

        // Load overridden package first
        $this->builder->loadPackage($overriddenPackage, $this->package1Root);
        $this->builder->loadPackage($overridingPackage, $this->package2Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideFuturePackage()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package2Root.'/override'));

        $overridingPackage = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'override',
                ),
                'override' => 'acme/overridden',
            ),
        ));

        $overriddenPackage = $this->createPackage(array(
            'name' => 'acme/overridden',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'resources',
                ),
            ),
        ));

        // Load overridden package last
        $this->builder->loadPackage($overridingPackage, $this->package2Root);
        $this->builder->loadPackage($overriddenPackage, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideChain()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package3Root.'/override2'));

        $package3 = $this->createPackage(array(
            'name' => 'acme/priority2',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'override2',
                ),
                'override' => 'acme/priority1',
            ),
        ));

        $package2 = $this->createPackage(array(
            'name' => 'acme/priority1',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'override',
                ),
                'override' => 'acme/priority0',
            ),
        ));

        $package1 = $this->createPackage(array(
            'name' => 'acme/priority0',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'resources',
                ),
            ),
        ));

        $this->builder->loadPackage($package1, $this->package1Root);
        $this->builder->loadPackage($package2, $this->package2Root);
        $this->builder->loadPackage($package3, $this->package3Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideMultiplePackages()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/overridden1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/overridden2', new LocalDirectoryResource($this->package2Root.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/acme/overridden1', new LocalDirectoryResource($this->package3Root.'/override1'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/acme/overridden2', new LocalDirectoryResource($this->package3Root.'/override2'));

        $overridingPackage = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden1' => 'override1',
                    '/acme/overridden2' => 'override2',
                ),
                'override' => array('acme/overridden1', 'acme/overridden2'),
            ),
        ));

        $overriddenPackage1 = $this->createPackage(array(
            'name' => 'acme/overridden1',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden1' => 'resources',
                ),
            ),
        ));

        $overriddenPackage2 = $this->createPackage(array(
            'name' => 'acme/overridden2',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden2' => 'resources',
                ),
            ),
        ));

        // Load overridden package first
        $this->builder->loadPackage($overriddenPackage1, $this->package1Root);
        $this->builder->loadPackage($overriddenPackage2, $this->package2Root);
        $this->builder->loadPackage($overridingPackage, $this->package3Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideNonExistingPackage()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package2Root.'/override'));

        $overridingPackage = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'override',
                ),
                'override' => 'acme/overridden',
            ),
        ));

        $this->builder->loadPackage($overridingPackage, $this->package2Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideWithMultipleDirectories()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package2Root.'/css-override'));

        $overridingPackage = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => array('override', 'css-override'),
                ),
                'override' => 'acme/overridden',
            ),
        ));

        $overriddenPackage = $this->createPackage(array(
            'name' => 'acme/overridden',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'resources',
                ),
            ),
        ));

        $this->builder->loadPackage($overridingPackage, $this->package2Root);
        $this->builder->loadPackage($overriddenPackage, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\Extension\Composer\RepositoryBuilder\ResourceConflictException
     */
    public function testConflictIfSamePathsButNoOverrideStatement()
    {
        $this->repo->expects($this->never())
            ->method('add');

        $overridingPackage1 = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'resources',
                ),
            ),
        ));

        $overridingPackage2 = $this->createPackage(array(
            'name' => 'acme/package2',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'override',
                ),
            ),
        ));

        $this->builder->loadPackage($overridingPackage1, $this->package1Root);
        $this->builder->loadPackage($overridingPackage2, $this->package2Root);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\Extension\Composer\RepositoryBuilder\ResourceConflictException
     */
    public function testConflictIfExistingSubPathAndNoOverrideStatement()
    {
        $this->repo->expects($this->never())
            ->method('add');

        $overridingPackage1 = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'resources',
                ),
            ),
        ));

        $overridingPackage2 = $this->createPackage(array(
            'name' => 'acme/package2',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden/config' => 'override',
                ),
            ),
        ));

        $this->builder->loadPackage($overridingPackage1, $this->package1Root);
        $this->builder->loadPackage($overridingPackage2, $this->package2Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testNoConflictIfNewSubPathAndNoOverrideStatement()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/overridden/new', new LocalDirectoryResource($this->package2Root.'/override'));

        $overridingPackage1 = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'resources',
                ),
            ),
        ));

        $overridingPackage2 = $this->createPackage(array(
            'name' => 'acme/package2',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden/new' => 'override',
                ),
            ),
        ));

        $this->builder->loadPackage($overridingPackage1, $this->package1Root);
        $this->builder->loadPackage($overridingPackage2, $this->package2Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testDefineOverrideOrderOnRootPackage()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/acme/overridden', new LocalDirectoryResource($this->package2Root.'/override'));

        $rootPackage = $this->createRootPackage(array(
            'extra' => array(
                'override-order' => array(
                    'acme/package1',
                    'acme/package2',
                ),
            ),
        ));

        $overridingPackage1 = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'resources',
                ),
            ),
        ));

        $overridingPackage2 = $this->createPackage(array(
            'name' => 'acme/package2',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'override',
                ),
            ),
        ));

        $this->builder->loadPackage($rootPackage, '/');
        $this->builder->loadPackage($overridingPackage1, $this->package1Root);
        $this->builder->loadPackage($overridingPackage2, $this->package2Root);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\Extension\Composer\RepositoryBuilder\ResourceConflictException
     */
    public function testOverrideOrderInNonRootPackageIsIgnored()
    {
        $this->repo->expects($this->never())
            ->method('add');

        $pseudoRootPackage = $this->createPackage(array(
            'extra' => array(
                'override-order' => array(
                    'acme/package2',
                    'acme/package1',
                ),
            ),
        ));

        $overridingPackage1 = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'resources',
                ),
            ),
        ));

        $overridingPackage2 = $this->createPackage(array(
            'name' => 'acme/package2',
            'extra' => array(
                'resources' => array(
                    '/acme/overridden' => 'override',
                ),
            ),
        ));

        $this->builder->loadPackage($pseudoRootPackage, '/');
        $this->builder->loadPackage($overridingPackage1, $this->package1Root);
        $this->builder->loadPackage($overridingPackage2, $this->package2Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagResources()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/acme/package', 'acme/tag');

        $package = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => array(
                    '/acme/package' => 'resources',
                ),
                'resource-tags' => array(
                    '/acme/package' => 'acme/tag',
                ),
            ),
        ));

        $this->builder->loadPackage($package, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagResourcesFromExistingOtherPackage()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/acme/package1', 'acme/tag');

        $package1 = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/package1' => 'resources',
                ),
            ),
        ));

        $package2 = $this->createPackage(array(
            'name' => 'acme/package2',
            'extra' => array(
                'resource-tags' => array(
                    '/acme/package1' => 'acme/tag',
                ),
            ),
        ));

        $this->builder->loadPackage($package1, $this->package1Root);
        $this->builder->loadPackage($package2, $this->package2Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagResourcesFromFutureOtherPackage()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/acme/package1', 'acme/tag');

        $package1 = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/package1' => 'resources',
                ),
            ),
        ));

        $package2 = $this->createPackage(array(
            'name' => 'acme/package2',
            'extra' => array(
                'resource-tags' => array(
                    '/acme/package1' => 'acme/tag',
                ),
            ),
        ));

        $this->builder->loadPackage($package2, $this->package2Root);
        $this->builder->loadPackage($package1, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagInTwoPackages()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/acme/package1', 'acme/tag1');

        $this->repo->expects($this->at(2))
            ->method('tag')
            ->with('/acme/package1', 'acme/tag2');

        $package1 = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/package1' => 'resources',
                ),
                'resource-tags' => array(
                    '/acme/package1' => 'acme/tag1',
                ),
            ),
        ));

        $package2 = $this->createPackage(array(
            'name' => 'acme/package2',
            'extra' => array(
                'resource-tags' => array(
                    '/acme/package1' => 'acme/tag2',
                ),
            ),
        ));

        $this->builder->loadPackage($package1, $this->package1Root);
        $this->builder->loadPackage($package2, $this->package2Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testDuplicateTags()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/acme/package1', 'acme/tag');

        $package1 = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/package1' => 'resources',
                ),
                'resource-tags' => array(
                    '/acme/package1' => 'acme/tag',
                ),
            ),
        ));

        $package2 = $this->createPackage(array(
            'name' => 'acme/package2',
            'extra' => array(
                'resource-tags' => array(
                    '/acme/package1' => 'acme/tag',
                ),
            ),
        ));

        $this->builder->loadPackage($package2, $this->package2Root);
        $this->builder->loadPackage($package1, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    public function testMultipleTags()
    {
        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/acme/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/acme/package1', 'acme/tag1');

        $this->repo->expects($this->at(2))
            ->method('tag')
            ->with('/acme/package1', 'acme/tag2');

        $package = $this->createPackage(array(
            'name' => 'acme/package1',
            'extra' => array(
                'resources' => array(
                    '/acme/package1' => 'resources',
                ),
                'resource-tags' => array(
                    '/acme/package1' => array('acme/tag1', 'acme/tag2'),
                ),
            ),
        ));

        $this->builder->loadPackage($package, $this->package1Root);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\Extension\Composer\RepositoryBuilder\ResourceDefinitionException
     */
    public function testResourcesMustBeArray()
    {
        $package = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => 'foobar',
            ),
        ));

        $this->builder->loadPackage($package, $this->package1Root);
    }

    /**
     * @expectedException \Puli\Extension\Composer\RepositoryBuilder\ResourceDefinitionException
     */
    public function testOverrideMustBeStringOrArray()
    {
        $package = $this->createPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resources' => new \stdClass(),
            ),
        ));

        $this->builder->loadPackage($package, $this->package1Root);
    }

    /**
     * @expectedException \Puli\Extension\Composer\RepositoryBuilder\ResourceDefinitionException
     */
    public function testOverrideOrderMustBeArray()
    {
        $package = $this->createRootPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'override-order' => 'foobar',
            ),
        ));

        $this->builder->loadPackage($package, '/');
    }

    /**
     * @expectedException \Puli\Extension\Composer\RepositoryBuilder\ResourceDefinitionException
     */
    public function testTagsMustBeArray()
    {
        $package = $this->createRootPackage(array(
            'name' => 'acme/package',
            'extra' => array(
                'resource-tags' => 'foobar',
            ),
        ));

        $this->builder->loadPackage($package, '/');
    }

    /**
     * @param array $config
     *
     * @return \Composer\Package\PackageInterface
     */
    private function createPackage(array $config)
    {
        $package = $this->getMock('\Composer\Package\PackageInterface');

        $package->expects($this->any())
            ->method('getName')
            ->will($this->returnValue(isset($config['name']) ? $config['name'] : ''));

        $package->expects($this->any())
            ->method('getExtra')
            ->will($this->returnValue(isset($config['extra']) ? $config['extra'] : array()));

        return $package;
    }

    /**
     * @param array $config
     *
     * @return \Composer\Package\PackageInterface
     */
    private function createRootPackage(array $config)
    {
        $package = $this->getMock('\Composer\Package\RootPackageInterface');

        $package->expects($this->any())
            ->method('getName')
            ->will($this->returnValue(isset($config['name']) ? $config['name'] : '__root__'));

        $package->expects($this->any())
            ->method('getExtra')
            ->will($this->returnValue(isset($config['extra']) ? $config['extra'] : array()));

        return $package;
    }
}
