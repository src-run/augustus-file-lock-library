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

use Psr\Log\NullLogger;
use SR\File\Lock\FileLock;

/**
 * @covers \SR\File\Lock\FileLock
 */
class FileLockTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruction()
    {
        $lock = new FileLock(__FILE__);

        $this->assertInstanceOf('SR\File\Lock\FileLock', $lock);
    }

    public function testConstructionWithLogger()
    {
        $lock = new FileLock(__FILE__, null, new NullLogger());

        $this->assertInstanceOf('SR\File\Lock\FileLock', $lock);
    }

    public function testDefaultOptions()
    {
        $lock = new FileLock(__FILE__);

        $this->assertTrue($lock->isShared());
        $this->assertTrue($lock->isNonBlocking());
        $this->assertFalse($lock->isExclusive());
        $this->assertFalse($lock->isBlocking());
        $this->assertFalse($lock->isAcquired());
        $this->assertNull($lock->getHandle());
    }

    /**
     * @expectedException \SR\File\Lock\Exception\InvalidOptionsException
     */
    public function testThrowsExceptionOnBlockingAndNonBlockingOptions()
    {
        new FileLock(__FILE__, FileLock::LOCK_BLOCKING | FileLock::LOCK_NON_BLOCKING);
    }

    /**
     * @expectedException \SR\File\Lock\Exception\InvalidOptionsException
     */
    public function testThrowsExceptionOnSharedAndExclusiveOptions()
    {
        new FileLock(__FILE__, FileLock::LOCK_SHARED | FileLock::LOCK_EXCLUSIVE);
    }

    public function testAcquireLock()
    {
        $lock = new FileLock(__FILE__);

        $lock->acquire();
        $this->assertTrue($lock->isAcquired());
        $this->assertTrue(is_resource($lock->getHandle()));

        $lock->release();
        $this->assertFalse($lock->isAcquired());
        $this->assertFalse(is_resource($lock->getHandle()));
    }

    /**
     * @expectedException \SR\File\Lock\Exception\FileLockReleaseException
     */
    public function testThrowsExceptionOnReleaseOfUnacquired()
    {
        $lock = new FileLock(__FILE__);

        $lock->release();
    }

    /**
     * @expectedException \SR\File\Lock\Exception\FileLockAcquireException
     */
    public function testThrowsExceptionOnAcquireForInvalidFile()
    {
        $lock = new FileLock(__FILE__.DIRECTORY_SEPARATOR.'does-not-exist.ext');

        $lock->acquire();
    }

    public function testOptions()
    {
        $lock = new FileLock(__FILE__, FileLock::LOCK_EXCLUSIVE | FileLock::LOCK_BLOCKING);

        $lock->acquire();

        $this->assertFalse($lock->isShared());
        $this->assertFalse($lock->isNonBlocking());
        $this->assertTrue($lock->isExclusive());
        $this->assertTrue($lock->isBlocking());
        $this->assertTrue($lock->isAcquired());

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
            ->with('Successfully acquired {desc} lock on file {file}.', [
                'desc' => 'shared, non-blocking',
                'file' => __FILE__,
            ])
            ->willReturn(null);

        $lock = new FileLock(__FILE__, null, $logger);
        $lock->acquire();

        $logger = $this
            ->getMockBuilder(NullLogger::class)
            ->setMethods(['debug'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Successfully released lock on file {file}.', [
                'file' => __FILE__,
            ])
            ->willReturn(null);

        $lock->setLogger($logger);
        $lock->release();
    }
}

/* EOF */
