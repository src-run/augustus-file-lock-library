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
    private $acquired = false;

    /**
     * FileLockInterface constructor.
     *
     * @param string          $file
     * @param null|int        $options
     * @param LoggerInterface $logger
     */
    final public function __construct($file, $options = null, LoggerInterface $logger = null)
    {
        if (self::LOCK_SHARED & $options && self::LOCK_EXCLUSIVE & $options) {
            throw new InvalidOptionsException('Lock cannot be both shared and exclusive.');
        }

        if (self::LOCK_NON_BLOCKING & $options && self::LOCK_BLOCKING & $options) {
            throw new InvalidOptionsException('Lock cannot be both non-blocking and blocking.');
        }

        $this->options = $options === null ? self::LOCK_SHARED | self::LOCK_NON_BLOCKING : $options;
        $this->file = $file;

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
     * @return bool
     */
    final public function acquire()
    {
        $type = LOCK_EX | LOCK_NB;
        $desc = 'exclusive';

        if (self::LOCK_SHARED & $this->options) {
            $type = LOCK_EX;
            $desc = 'shared';
        }

        if (self::LOCK_BLOCKING & $this->options) {
            $type ^= LOCK_NB;
            $desc .= ', blocking';
        }

        if (!$this->fileLock($type)) {
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
        if (!$this->hasResource() || !$this->fileLock(LOCK_UN) || !fclose($this->fileResource)) {
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
     * @param int $operation
     *
     * @throws FileResourceException
     *
     * @return bool
     */
    private function fileLock($operation)
    {
        if (!$this->hasResource()) {
            $this->fileResource = @fopen($this->file, 'c+');
        }

        if (!$this->hasResource()) {
            return false;
        }

        return flock($this->fileResource, $operation);
    }
}

/* EOF */
