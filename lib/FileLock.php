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
use SR\File\Lock\Exception\InvalidOptionException;
use SR\Log\LoggerAwareTrait;

/**
 * Simple API for acquiring and release file locks.
 */
class FileLock implements FileLockInterface
{
    use LoggerAwareTrait;

    /**
     * @var \SplFileInfo
     */
    private $file;

    /**
     * @var resource
     */
    private $handle;

    /**
     * @var int
     */
    protected $options;

    /**
     * @var bool
     */
    protected $acquired;

    /**
     * Construct file lock with file name and optional options bit mask and logger instance.
     *
     * @param string|\SplFileInfo $file    The path to the file to lock
     * @param null|int            $options An options bitmark to configure locking behavior
     * @param LoggerInterface     $logger  A logger instance enables acquire/release logging
     */
    public function __construct(\SplFileInfo $file, int $options = null, LoggerInterface $logger = null)
    {
        $this->setFile($file);
        $this->setOptions($options);
        $this->setLogger($logger);
    }

    /**
     * Construct file lock with file path or \SplFileInfo and an options bit mask.
     *
     * @param string|\SplFileInfo $file    The path to the file to lock
     * @param null|int            $options An options bitmark to configure locking behavior
     *
     * @return static|FileLockInterface
     */
    public static function create($file, int $options = null) : FileLockInterface
    {
        return new static($file instanceof \SplFileInfo ? $file : new \SplFileInfo($file), $options);
    }

    /**
     * Assign the options bit mask to configure locking behavior.
     *
     * @param int $options An options bitmask to configure locking behavior
     *
     * @throws InvalidOptionException If conflicting options are passed
     *
     * @return FileLockInterface
     */
    public function setOptions($options) : FileLockInterface
    {
        if (FileLockInterface::LOCK_SHARED & $options && FileLockInterface::LOCK_EXCLUSIVE & $options) {
            throw new InvalidOptionException('Lock cannot be both shared and exclusive.');
        }

        if (FileLockInterface::LOCK_NON_BLOCKING & $options && FileLockInterface::LOCK_BLOCKING & $options) {
            throw new InvalidOptionException('Lock cannot be both non-blocking and blocking.');
        }

        $this->options = $options === null ? FileLockInterface::LOCK_SHARED | FileLockInterface::LOCK_NON_BLOCKING : $options;

        return $this;
    }

    /**
     * Assign (or re-assign) the file to lock.
     *
     * @param \SplFileInfo $file The file to lock as an \SplFileInfo object
     *
     * @return FileLockInterface
     */
    public function setFile(\SplFileInfo $file) : FileLockInterface
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Returns true if file handle is held.
     *
     * @return bool
     */
    public function hasResource() : bool
    {
        return is_resource($this->handle);
    }

    /**
     * Returns the file handle.
     *
     * @return resource
     */
    public function getResource()
    {
        return $this->handle;
    }

    /**
     * Returns true if lock has been acquired.
     *
     * @return bool
     */
    public function isAcquired() : bool
    {
        return $this->acquired === true;
    }

    /**
     * Returns true if lock is shared.
     *
     * @return bool
     */
    public function isShared() : bool
    {
        return (bool) (FileLockInterface::LOCK_SHARED & $this->options);
    }

    /**
     * Returns true if lock is exclusive.
     *
     * @return bool
     */
    public function isExclusive() : bool
    {
        return (bool) (FileLockInterface::LOCK_EXCLUSIVE & $this->options);
    }

    /**
     * Returns true if lock is blocking.
     *
     * @return bool
     */
    public function isBlocking() : bool
    {
        return (bool) (FileLockInterface::LOCK_BLOCKING & $this->options);
    }

    /**
     * Returns true if lock is non blocking.
     *
     * @return bool
     */
    public function isNonBlocking() : bool
    {
        return (bool) (FileLockInterface::LOCK_NON_BLOCKING & $this->options);
    }

    /**
     * Try to acquire a file lock.
     *
     * @return FileLockInterface
     */
    public function acquire() : FileLockInterface
    {
        if (!$this->fileLock($this->getAcquireOperation())) {
            $this->logDebug(
                'Failed to acquire {desc} lock on {file}.',
                $this->getLogReplacements()
            );

            throw new FileLockAcquireException(
                'Failed to acquire %s lock on %s',
                ...$this->getExceptionReplacements()
            );
        }

        $this->acquired = true;

        $this->logDebug(
            'Successfully acquired {desc} lock on {file}.',
            $this->getLogReplacements()
        );

        return $this;
    }

    /**
     * Try to release a file lock.
     *
     * @return FileLockInterface
     */
    public function release() : FileLockInterface
    {
        if (!$this->hasResource() || !$this->fileLock(LOCK_UN) || !fclose($this->handle)) {
            $this->logDebug(
                'Failed to release {desc} lock on {file}.',
                $this->getLogReplacements()
            );

            throw new FileLockReleaseException(
                'Failed to release %s lock on %s',
                ...$this->getExceptionReplacements()
            );
        }

        $this->acquired = false;

        $this->logDebug(
            'Successfully released {desc} lock on {file}.',
            $this->getLogReplacements()
        );

        return $this;
    }

    /**
     * @param int $operation The flock bitmask operation
     *
     * @return bool
     */
    private function fileLock($operation) : bool
    {
        if (!$this->hasResource()) {
            $this->handle = @fopen($this->file->getPathname(), 'c+');
        }

        if (!$this->hasResource()) {
            return false;
        }

        return flock($this->handle, $operation);
    }

    /**
     * @return int
     */
    private function getAcquireOperation() : int
    {
        $operation = self::LOCK_SHARED & $this->options ? LOCK_EX : LOCK_EX | LOCK_NB;

        if (self::LOCK_BLOCKING & $this->options) {
            $operation ^= LOCK_NB;
        }

        return $operation;
    }

    /**
     * @return string[]
     */
    private function getExceptionReplacements() : array
    {
        return array_values($this->getLogReplacements());
    }

    /**
     * @return string[]
     */
    private function getLogReplacements() : array
    {
        return [
            'desc' => $this->getReplacementDescription(),
            'file' => $this->file->getPathname(),
        ];
    }

    /**
     * @return string
     */
    private function getReplacementDescription() : string
    {
        $desc = self::LOCK_SHARED & $this->options ? 'shared' : 'exclusive';
        $desc .= self::LOCK_BLOCKING & $this->options ? ' (blocking)' : ' (non-blocking)';

        return $desc;
    }
}
