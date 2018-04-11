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

use SR\Log\LoggerAwareInterface;

/**
 * Interface for single-file locks.
 */
interface FileLockInterface extends LockOptionsInterface, LoggerAwareInterface
{
    /**
     * Assign (or re-assign) the file to lock.
     *
     * @param \SplFileInfo $file The file to lock as an \SplFileInfo object
     *
     * @return FileLockInterface
     */
    public function setFile(\SplFileInfo $file): self;

    /**
     * Assign the options bitmask to configure locking behavior.
     *
     * @param int $options An options bitmark to configure locking behavior
     *
     * @return FileLockInterface
     */
    public function setOptions($options): self;

    /**
     * Returns true if lock has been acquired.
     *
     * @return bool
     */
    public function isAcquired(): bool;

    /**
     * Returns true if lock is shared.
     *
     * @return bool
     */
    public function isShared(): bool;

    /**
     * Returns true if lock is exclusive.
     *
     * @return bool
     */
    public function isExclusive(): bool;

    /**
     * Returns if lock is blocking.
     *
     * @return bool
     */
    public function isBlocking(): bool;

    /**
     * Returns true if lock is non blocking.
     *
     * @return bool
     */
    public function isNonBlocking(): bool;

    /**
     * Returns true if file handle is held.
     *
     * @return bool
     */
    public function hasResource(): bool;

    /**
     * Returns the file handle.
     *
     * @return resource
     */
    public function getResource();

    /**
     * Try to acquire a file lock.
     *
     * @return FileLockInterface
     */
    public function acquire(): self;

    /**
     * Try to release a file lock.
     *
     * @return FileLockInterface
     */
    public function release(): self;
}
