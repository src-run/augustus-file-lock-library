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
 * Interface for multi-file locks.
 */
interface FileLockGroupInterface extends LockOptionsInterface, LoggerAwareInterface
{
    /**
     * @param null|int                $options
     * @param \SplFileInfo[]|string[] ...$files
     *
     * @return static|FileLockGroupInterface
     */
    public static function create(int $options = null, ...$files): self;

    /**
     * @param \SplFileInfo[] ...$locks
     *
     * @return FileLockGroupInterface
     */
    public function setLocks(\SplFileInfo ...$locks): self;

    /**
     * Returns array of file locks.
     *
     * @return FileLock[]
     */
    public function getLocks(): array;

    /**
     * Returns true if lock group has been acquired.
     *
     * @return bool
     */
    public function isAcquired(): bool;

    /**
     * Try to acquire a file lock group.
     *
     * @return FileLockGroupInterface
     */
    public function acquire(): self;

    /**
     * Try to release a file lock.
     *
     * @return FileLockGroupInterface
     */
    public function release(): self;
}
