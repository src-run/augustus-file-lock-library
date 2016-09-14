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
use SR\File\Lock\Exception\InvalidOptionsException;
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
    private $file;

    /**
     * @var int
     */
    private $options;

    /**
     * @var resource
     */
    private $handle;

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
        $this->file = $file;
        $this->acquired = false;

        $this->setOptions($options);

        if ($logger) {
            $this->setLogger($logger);
        }
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
     * Returns the file handle.
     *
     * @return resource
     */
    final public function getHandle()
    {
        return $this->handle;
    }

    /**
     * Try to acquire a file lock.
     *
     * @return bool
     */
    final public function acquire()
    {
        $type = LOCK_EX;
        $desc = 'exclusive';

        if (self::LOCK_SHARED & $this->options) {
            $type = LOCK_EX;
            $desc = 'shared';
        }

        if (self::LOCK_NON_BLOCKING & $this->options) {
            $type |= LOCK_NB;
            $desc .= ', non-blocking';
        }

        if (!$this->flockOperation($type)) {
            $this->logDebug('Could not acquire {desc} lock on file {file}.', [
                'desc' => $desc,
                'file' => $this->file,
            ]);

            throw new FileLockAcquireException('Could not acquire %s lock on file "%s"', $desc, $this->file);
        }

        $this->acquired = true;

        $this->logDebug('Successfully acquired {desc} lock on file {file}.', [
            'desc' => $desc,
            'file' => $this->file,
        ]);
    }

    /**
     * Release a file lock.
     *
     * @return bool
     */
    final public function release()
    {
        if (!is_resource($this->handle) || !$this->flockOperation(LOCK_UN) || !fclose($this->handle)) {
            $this->logDebug('Could not release lock on file {file}.', [
                'file' => $this->file,
            ]);

            throw new FileLockReleaseException('Could not release lock on file "%s"', $this->file);
        }

        $this->acquired = false;

        $this->logDebug('Successfully released lock on file {file}.', [
            'file' => $this->file,
        ]);

        return true;
    }

    /**
     * @param int|null $options
     *
     * @throws InvalidOptionsException
     */
    private function setOptions($options)
    {
        if ($options === null) {
            $options = self::LOCK_SHARED;
            $options |= self::LOCK_NON_BLOCKING;
        }

        if (self::LOCK_SHARED & $options && self::LOCK_EXCLUSIVE & $options) {
            throw new InvalidOptionsException('Lock for "%s" cannot be both shared and exclusive.', $this->file);
        }

        if (self::LOCK_NON_BLOCKING & $options && self::LOCK_BLOCKING & $options) {
            throw new InvalidOptionsException('Lock for "%s" cannot be both non blocking and blocking.', $this->file);
        }

        $this->options = $options;
    }

    /**
     * @param int $operation
     *
     * @throws FileResourceException
     *
     * @return bool
     */
    private function flockOperation($operation)
    {
        if (!$this->handle) {
            $this->handle = @fopen($this->file, 'c');
        }

        if (!is_resource($this->handle)) {
            return false;
        }

        return flock($this->handle, $operation);
    }
}

/* EOF */
