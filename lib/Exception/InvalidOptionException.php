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

use SR\Exception\Logic\InvalidArgumentException;

/**
 * Exception used for when an invalid option or combination of options are during object setup/config.
 */
class InvalidOptionException extends InvalidArgumentException
{
}
