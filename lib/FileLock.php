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
use SR\File\Lock\Exception\FileResourceException;
use SR\File\Lock\Exception\InvalidOptionException;
use SR\Log\LoggerAwareTrait;

/**
 * File lock.
 */
class FileLock implements FileLockInterface
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    private $fileName;

    /**
     * @var resource
     */
    private $fileResource;

    /**
     * @var int
     */
    private $options;

    /**
     * @var bool
     */
    private $acquired;

    /**
     * FileLockInterface constructor.
     *
     * @param string          $file
     * @param null|int        $options
     * @param LoggerInterface $logger
     */
    final public function __construct($file, $options = null, LoggerInterface $logger = null)
    {
        $this->fileName = $file;

        $this->setOptions($options);
        $this->setLogger($logger);
    }

    /**
     * Set file lock options.
     *
     * @param int $options
     *
     * @throws InvalidOptionException
     */
    final public function setOptions($options)
    {
        if (self::LOCK_SHARED & $options && self::LOCK_EXCLUSIVE & $options) {
            throw new InvalidOptionException('Lock cannot be both shared and exclusive.');
        }

        if (self::LOCK_NON_BLOCKING & $options && self::LOCK_BLOCKING & $options) {
            throw new InvalidOptionException('Lock cannot be both non-blocking and blocking.');
        }

        $this->options = $options === null ? self::LOCK_SHARED | self::LOCK_NON_BLOCKING : $options;
    }

    /**
     * Returns whether lock has been acquired.
     *
     * @return bool
     */
    final public function isAcquired()
    {
        return $this->acquired === true;
    }

    /**
     * Returns if lock is shared.
     *
     * @return bool
     */
    final public function isShared()
    {
        return (bool) (self::LOCK_SHARED & $this->options);
    }

    /**
     * Returns if lock is exclusive.
     *
     * @return bool
     */
    final public function isExclusive()
    {
        return (bool) (self::LOCK_EXCLUSIVE & $this->options);
    }

    /**
     * Returns if lock is blocking.
     *
     * @return bool
     */
    final public function isBlocking()
    {
        return (bool) (self::LOCK_BLOCKING & $this->options);
    }

    /**
     * Returns if lock is non blocking.
     *
     * @return bool
     */
    final public function isNonBlocking()
    {
        return (bool) (self::LOCK_NON_BLOCKING & $this->options);
    }

    /**
     * Returns if file handle is held.
     *
     * @return bool
     */
    final public function hasResource()
    {
        return is_resource($this->fileResource);
    }

    /**
     * Returns the file handle.
     *
     * @return resource
     */
    final public function getResource()
    {
        return $this->fileResource;
    }

    /**
     * Try to acquire a file lock.
     *
     * @return $this
     */
    final public function acquire()
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
     * Release a file lock.
     *
     * @return bool
     */
    final public function release()
    {
        if (!$this->hasResource() || !$this->fileLock(LOCK_UN) || !fclose($this->fileResource)) {
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

        return true;
    }

    /**
     * @param int $operation
     *
     * @throws FileResourceException
     *
     * @return bool
     */
    private function fileLock($operation)
    {
        if (!$this->hasResource()) {
            $this->fileResource = @fopen($this->fileName, 'c+');
        }

        if (!$this->hasResource()) {
            return false;
        }

        return flock($this->fileResource, $operation);
    }

    /**
     * @return int
     */
    private function getAcquireOperation()
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
    private function getExceptionReplacements()
    {
        return array_values($this->getLogReplacements());
    }

    /**
     * @return string[]
     */
    private function getLogReplacements()
    {
        return [
            'desc' => $this->getReplacementDescription(),
            'file' => $this->fileName,
        ];
    }

    /**
     * @return string
     */
    private function getReplacementDescription()
    {
        $desc = self::LOCK_SHARED & $this->options ? 'shared' : 'exclusive';
        $desc .= self::LOCK_BLOCKING & $this->options ? ' (blocking)' : ' (non-blocking)';

        return $desc;
    }
}

/* EOF */
