<?php

/*
 * This file is part of the `src-run/augustus-file-lock-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace SR\File\Lock\Exception;

use SR\Exception\Runtime\RuntimeException;

/**
 * Exception used for when the file resource handle could not be retrieved or another resource error occurs.
 */
class FileResourceException extends RuntimeException
{
}
