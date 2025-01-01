<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source\Helper;

use SplFileInfo;

use function realpath;

final class FilePath
{
    /** @throws void */
    public function getPath(SplFileInfo $file): string | null
    {
        $realpath = realpath($file->getPathname());

        if ($realpath === false) {
            return null;
        }

        return match ($file->getExtension()) {
            'gz' => 'compress.zlib://' . $realpath,
            'bz2' => 'compress.bzip2://' . $realpath,
            'tgz' => 'phar://' . $realpath,
            default => $realpath,
        };
    }
}
