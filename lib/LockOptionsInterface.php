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

/**
 * Interface containing file lock options.
 */
interface LockOptionsInterface
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
}
