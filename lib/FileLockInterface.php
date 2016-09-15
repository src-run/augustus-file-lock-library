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
use SR\Log\LoggerAwareInterface;

/**
 * Interface that a file lock.
 */
interface FileLockInterface extends LoggerAwareInterface
{
    /**
     * Option for shared lock.
     */
    const LOCK_SHARED = 1;

    /**
     * Option for exclusive lock.
     */
    const LOCK_EXCLUSIVE = 2;

    /**
     * Option for non-blocking lock acquisition.
     */
    const LOCK_NON_BLOCKING = 4;

    /**
     * Option for blocking lock acquisition.
     */
    const LOCK_BLOCKING = 8;

    /**
     * FileLockInterface constructor.
     *
     * @param string          $file
     * @param null|int        $options
     * @param LoggerInterface $logger
     */
    public function __construct($file, $options = null, LoggerInterface $logger = null);

    /**
     * Returns whether lock has been acquired.
     *
     * @return bool
     */
    public function isAcquired();

    /**
     * Returns if lock is shared.
     *
     * @return bool
     */
    public function isShared();

    /**
     * Returns if lock is exclusive.
     *
     * @return bool
     */
    public function isExclusive();

    /**
     * Returns if lock is blocking.
     *
     * @return bool
     */
    public function isBlocking();

    /**
     * Returns if lock is non blocking.
     *
     * @return bool
     */
    public function isNonBlocking();

    /**
     * Returns if file handle is held.
     *
     * @return bool
     */
    public function hasResource();

    /**
     * Returns the file handle.
     *
     * @return resource
     */
    public function getResource();

    /**
     * Try to acquire a file lock.
     *
     * @return bool
     */
    public function acquire();

    /**
     * Release a file lock.
     *
     * @return bool
     */
    public function release();
}

/* EOF */
