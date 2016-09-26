<?php

/*
 * This file is part of the `src-run/augustus-file-lock-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace SR\File\Lock;

use Psr\Log\LoggerInterface;
use SR\File\Lock\Exception\FileLockAcquireException;
use SR\File\Lock\Exception\FileLockReleaseException;
use SR\Log\LoggerAwareTrait;

/**
 * Simple API for acquiring and release file locks as group of multiple.
 */
class FileLockGroup implements FileLockGroupInterface
{
    use LoggerAwareTrait;

    /**
     * @var FileLock[]
     */
    private $locks = [];

    /**
     * @var int
     */
    private $options;

    /**
     * @param null|int        $options
     * @param LoggerInterface $logger
     * @param \SplFileInfo[] ...$files
     */
    public function __construct(int $options = null, LoggerInterface $logger = null, \SplFileInfo ...$files)
    {
        $this->options = $options;
        $this->setLogger($logger);

        $this->setLocks(...$files);
    }

    /**
     * @param null|int $options
     * @param \SplFileInfo[]|string[] ...$files
     *
     * @return static|FileLockGroupInterface
     */
    public static function create(int $options = null, ...$files) : FileLockGroupInterface
    {
        return new static($options, null, ...array_map(function ($file) {
            return $file instanceof \SplFileInfo ? $file : new \SplFileInfo($file);
        }, $files));
    }

    /**
     * @param \SplFileInfo[] ...$locks
     *
     * @return FileLockGroupInterface
     */
    public function setLocks(\SplFileInfo ...$locks) : FileLockGroupInterface
    {
        $this->locks = array_map(function (\SplFileInfo $file) {
            return new FileLock($file);
        }, $locks);

        return $this;
    }

    /**
     * Returns array of file locks.
     *
     * @return FileLock[]
     */
    public function getLocks() : array
    {
        return $this->locks;
    }

    /**
     * Returns true if lock group has been acquired.
     *
     * @return bool
     */
    public function isAcquired() : bool
    {
        foreach ($this->locks as $lock) {
            if (!$lock->isAcquired()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Try to acquire a file lock group.
     *
     * @return FileLockGroupInterface
     */
    public function acquire() : FileLockGroupInterface
    {
        try {
            array_walk($this->locks, function (FileLock $lock) {
                $lock->setLogger($this->logger);
                $lock->setOptions($this->options);
                $lock->acquire();
            });
        } catch (FileLockAcquireException $previous) {
            $rollback = $this->rollbackAcquiredLocks();

            $this->logDebug('Failed to acquire lock on group of {number} files; rolled back {rollback} files.', [
                'number' => count($this->locks),
                'rollback' => count($rollback),
            ]);

            throw new FileLockAcquireException(
                'Failed to acquire lock on group of %d files; rolled back %d files.', $previous, count($this->locks), count($rollback)
            );
        }

        $this->logDebug('Successfully acquired {number} locks on grouped files.', [
            'number' => count($this->locks),
        ]);

        return $this;
    }

    /**
     * Try to release a file lock.
     *
     * @return FileLockGroupInterface
     */
    public function release() : FileLockGroupInterface
    {
        try {
            array_walk($this->locks, function (FileLock $lock) {
                $lock->release();
            });
        } catch (FileLockReleaseException $previous) {
            $this->logDebug('Failed to release lock on group of {number} files.', [
                'number' => count($this->locks),
            ]);

            throw new FileLockReleaseException(
                'Failed to release lock on group of %d files.', $previous, count($this->locks)
            );
        }

        $this->logDebug('Successfully released {number} locks on grouped files.', [
            'number' => count($this->locks),
        ]);

        return $this;
    }

    /**
     * @return array|FileLock[]
     */
    private function rollbackAcquiredLocks()
    {
        return array_filter($this->locks, function (FileLock $lock) {
            return $lock->isAcquired() && $lock->release() && true;
        });
    }
}

/* EOF */
