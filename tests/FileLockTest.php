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

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SR\File\Lock\Exception\FileLockAcquireException;
use SR\File\Lock\Exception\FileLockReleaseException;
use SR\File\Lock\Exception\InvalidOptionException;
use SR\File\Lock\FileLock;

/**
 * @covers \SR\File\Lock\FileLock
 */
class FileLockTest extends TestCase
{
    public function testConstruction()
    {
        $lock = new FileLock(new \SplFileInfo(__FILE__));

        $this->assertInstanceOf('SR\File\Lock\FileLock', $lock);
    }

    public function testConstructionWithLogger()
    {
        $lock = new FileLock(new \SplFileInfo(__FILE__), null, new NullLogger());

        $this->assertInstanceOf('SR\File\Lock\FileLock', $lock);
    }

    public function testDefaultOptions()
    {
        $lock = new FileLock(new \SplFileInfo(__FILE__));

        $this->assertTrue($lock->isShared());
        $this->assertTrue($lock->isNonBlocking());
        $this->assertFalse($lock->isExclusive());
        $this->assertFalse($lock->isBlocking());
        $this->assertFalse($lock->isAcquired());
        $this->assertNull($lock->getResource());
        $this->assertFalse($lock->hasResource());
    }

    public function testThrowsExceptionOnBlockingAndNonBlockingOptions()
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Lock cannot be both non-blocking and blocking.');

        new FileLock(new \SplFileInfo(__FILE__), FileLock::LOCK_BLOCKING | FileLock::LOCK_NON_BLOCKING);
    }

    public function testThrowsExceptionOnSharedAndExclusiveOptions()
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Lock cannot be both shared and exclusive.');

        new FileLock(new \SplFileInfo(__FILE__), FileLock::LOCK_SHARED | FileLock::LOCK_EXCLUSIVE);
    }

    public function testAcquireLock()
    {
        $lock = new FileLock(new \SplFileInfo(__FILE__));

        $lock->acquire();
        $this->assertTrue($lock->isAcquired());
        $this->assertIsResource($lock->getResource());

        $lock->release();
        $this->assertFalse($lock->isAcquired());
        $this->assertIsResource($lock->getResource());
    }

    public function testThrowsExceptionOnReleaseOfUnacquired()
    {
        $lock = new FileLock(new \SplFileInfo(__FILE__));

        $this->expectException(FileLockReleaseException::class);
        $this->expectExceptionMessageMatches('{Failed to release .+ lock on .+}');

        $lock->release();
    }

    public function testThrowsExceptionOnAcquireForInvalidFile()
    {
        $lock = FileLock::create(__FILE__.DIRECTORY_SEPARATOR.'does-not-exist.ext');

        $this->expectException(FileLockAcquireException::class);
        $this->expectExceptionMessageMatches('{Failed to acquire .+ lock on .+}');

        $lock->acquire();
    }

    public function testOptions()
    {
        $lock = FileLock::create(__FILE__, FileLock::LOCK_EXCLUSIVE | FileLock::LOCK_BLOCKING);

        $lock->acquire();

        $this->assertFalse($lock->isShared());
        $this->assertFalse($lock->isNonBlocking());
        $this->assertTrue($lock->isExclusive());
        $this->assertTrue($lock->isBlocking());
        $this->assertTrue($lock->isAcquired());
        $this->assertTrue($lock->hasResource());

        $lock->release();

        $this->assertFalse($lock->isAcquired());
    }

    public function testLogger()
    {
        $logger = $this
            ->getMockBuilder(NullLogger::class)
            ->setMethods(['debug'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Successfully acquired {desc} lock on {file}.', [
                'desc' => 'shared (non-blocking)',
                'file' => __FILE__,
            ])
            ->willReturn(null);

        $lock = new FileLock(new \SplFileInfo(__FILE__), null, $logger);
        $lock->acquire();

        $logger = $this
            ->getMockBuilder(NullLogger::class)
            ->setMethods(['debug'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Successfully released {desc} lock on {file}.', [
                'desc' => 'shared (non-blocking)',
                'file' => __FILE__,
            ])
            ->willReturn(null);

        $lock->setLogger($logger);
        $lock->release();
    }

    public function testReleaseLogger()
    {
        $logger = $this
            ->getMockBuilder(NullLogger::class)
            ->setMethods(['debug'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Failed to release {desc} lock on {file}.', [
                'desc' => 'shared (non-blocking)',
                'file' => __FILE__,
            ])
            ->willReturn(null);

        $lock = new FileLock(new \SplFileInfo(__FILE__), null, $logger);

        $this->expectException(FileLockReleaseException::class);
        $this->expectExceptionMessageMatches('{Failed to release .+ lock on .+}');

        $lock->release();
    }

    public function testAcquireLogger()
    {
        $logger = $this
            ->getMockBuilder(NullLogger::class)
            ->setMethods(['debug'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Failed to acquire {desc} lock on {file}.', [
                'file' => __FILE__.DIRECTORY_SEPARATOR.'does-not-exist.ext',
                'desc' => 'shared (non-blocking)',
            ])
            ->willReturn(null);

        $lock = FileLock::create(__FILE__.DIRECTORY_SEPARATOR.'does-not-exist.ext', null)
            ->setLogger($logger);

        $this->expectException(FileLockAcquireException::class);
        $this->expectExceptionMessageMatches('{Failed to acquire .+ lock on .+}');

        $lock->acquire();
    }

    public function testExclusiveLock()
    {
        $lock1 = new FileLock(new \SplFileInfo(__FILE__), FileLock::LOCK_EXCLUSIVE | FileLock::LOCK_NON_BLOCKING);
        $lock1->acquire();

        $lock2 = new FileLock(new \SplFileInfo(__FILE__), FileLock::LOCK_EXCLUSIVE | FileLock::LOCK_NON_BLOCKING);

        $this->expectException(FileLockAcquireException::class);
        $this->expectExceptionMessageMatches('{Failed to acquire .+ lock on .+}');

        $lock2->acquire();
    }

    public function testCreateFileOnLock()
    {
        $file = tempnam(sys_get_temp_dir(), 'lock-test');
        unlink($file);
        $this->assertFileDoesNotExist($file);

        $lock = FileLock::create($file);
        $lock->acquire();
        $this->assertFileExists($file);

        $lock->release();
        unlink($file);
    }
}
