<?php

/*
 * This file is part of the `src-run/augustus-file-lock-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace SR\File\Lock\Tests;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;
use SR\File\Lock\Exception\FileLockAcquireException;
use SR\File\Lock\Exception\FileLockReleaseException;
use SR\File\Lock\FileLock;
use SR\File\Lock\FileLockGroup;

/**
 * @covers \SR\File\Lock\FileLockGroup
 */
class FileLockGroupTest extends TestCase
{
    public function testConstruction()
    {
        $lock = new FileLockGroup(null, null, ...$this->getFiles(true));

        $this->assertInstanceOf(FileLockGroup::class, $lock);
        $this->assertFalse($lock->isAcquired());
    }

    public function testAcquireLock()
    {
        $lock = new FileLockGroup(null, null, ...$this->getFiles(true));

        $this->assertFalse($lock->isAcquired());
        $lock->acquire();
        $this->assertTrue($lock->isAcquired());

        $lock->release();
        $this->assertFalse($lock->isAcquired());
    }

    public function testThrowsExceptionOnFileLockAquireInnerException()
    {
        $lock = new FileLockGroup(null, null, ...$this->getFiles(true));

        $this->expectException(FileLockReleaseException::class);

        $lock->release();
    }

    public function testSuccessfulAquireUsingMockedLocks()
    {
        $lock = new FileLockGroup();
        $prop = (new \ReflectionObject($lock))->getProperty('locks');
        $prop->setAccessible(true);
        $prop->setValue($lock, $locks = $this->getFileLocks(20, function (MockBuilder $builder) {
            $builder
                ->disableOriginalConstructor()
                ->setMethods(['acquire', 'release', 'isAcquired']);
        }));

        foreach ($locks as $mock) {
            $mock
                ->expects($this->once())
                ->method('acquire')
                ->withAnyParameters()
                ->will($this->returnValue($mock));

            $mock
                ->expects($this->atLeast(1))
                ->method('isAcquired')
                ->withAnyParameters()
                ->will($this->returnValue(true));
        }

        $lock->acquire();

        $this->assertTrue($lock->isAcquired());
    }

    public function testThrowsExceptionOnFileLockReleaseInnerException()
    {
        $lock = new FileLockGroup();
        $prop = (new \ReflectionObject($lock))->getProperty('locks');
        $prop->setAccessible(true);
        $prop->setValue($lock, $locks = $this->getFileLocks(20, function (MockBuilder $builder) {
            $builder
                ->disableOriginalConstructor()
                ->setMethods(['acquire', 'release']);
        }));

        foreach ($locks as $mock) {
            $mock
                ->expects($this->once())
                ->method('acquire')
                ->withAnyParameters()
                ->will($this->returnValue($mock));
        }

        $locks[0]
            ->expects($this->once())
            ->method('release')
            ->withAnyParameters()
            ->will($this->throwException(FileLockReleaseException::create()));

        $lock->acquire();

        $this->expectException(FileLockReleaseException::class);
        $this->expectExceptionMessage('Failed to release lock on group of');

        $lock->release();
    }

    public function testThrowsExceptionOnFileLockAcquireInnerException()
    {
        $lock = new FileLockGroup();
        $prop = (new \ReflectionObject($lock))->getProperty('locks');
        $prop->setAccessible(true);
        $prop->setValue($lock, $locks = $this->getFileLocks(20, function (MockBuilder $builder) {
            $builder
                ->disableOriginalConstructor()
                ->setMethods(['acquire']);
        }));

        $locks[0]
            ->expects($this->once())
            ->method('acquire')
            ->withAnyParameters()
            ->will($this->throwException(FileLockAcquireException::create()));

        $this->expectException(FileLockAcquireException::class);
        $this->expectExceptionMessage('Failed to acquire lock on group of');

        $lock->acquire();
    }

    public function testLocksSetter()
    {
        $files = $this->getFiles(true);
        $lock = FileLockGroup::create(null, ...$files);
        $prop = (new \ReflectionObject($lock))->getProperty('locks');
        $prop->setAccessible(true);

        $this->assertCount(count($files), $prop->getValue($lock));
        $this->assertSame($prop->getValue($lock), $lock->getLocks());

        $lock->setLocks(...array_slice($files, 0, 2));

        $this->assertCount(2, $prop->getValue($lock));
        $this->assertSame($prop->getValue($lock), $lock->getLocks());
    }

    /**
     * @param int           $count
     * @param \Closure|null $configureMock
     *
     * @return \PHPUnit_Framework_MockObject_MockObject[]|FileLock[]
     */
    public function getFileLocks($count = 20, \Closure $configureMock = null)
    {
        $files = [];

        foreach (range(0, $count - 1) as $i) {
            $mock = $this->getMockBuilder(FileLock::class);

            if ($configureMock) {
                $configureMock($mock);
            }

            $files[$i] = $mock->getMock();
        }

        return $files;
    }

    /**
     * @param bool $fileObjects
     *
     * @return string[]|\SplFileInfo[]
     */
    public function getFiles($fileObjects = false)
    {
        $base = __DIR__.'/../lib/';
        $files = array_filter(scandir($base), function ($file) use ($base) {
            return '.' !== $file && '..' !== $file && is_file($base.$file);
        });

        if (!$fileObjects) {
            return $files;
        }

        return array_map(function ($file) use ($base) {
            return new \SplFileInfo(realpath($base.$file));
        }, $files);
    }
}
