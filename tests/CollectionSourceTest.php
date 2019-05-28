<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2019, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\SourceTest;

use BrowscapHelper\Source\CollectionSource;
use BrowscapHelper\Source\SourceInterface;
use PHPUnit\Framework\TestCase;

final class CollectionSourceTest extends TestCase
{
    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function testConstruct(): void
    {
        /** @var SourceInterface $sourceOne */
        $sourceOne = $this->createMock(SourceInterface::class);
        /** @var SourceInterface $sourceTwo */
        $sourceTwo = $this->createMock(SourceInterface::class);

        $object = new CollectionSource($sourceOne, $sourceTwo);

        static::assertSame(2, $object->count());
    }
}
