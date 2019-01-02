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
namespace BrowscapHelper\Source;

class CollectionSource implements SourceInterface
{
    /**
     * @var \BrowscapHelper\Source\SourceInterface[]
     */
    private $collection;

    /**
     * @param \BrowscapHelper\Source\SourceInterface[] $collection
     */
    public function __construct(array $collection)
    {
        foreach ($collection as $source) {
            if (!$source instanceof SourceInterface) {
                throw new SourceException('unsupported type of source found');
            }

            $this->collection[] = $source;
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'collection';
    }

    /**
     * @return iterable|string[]
     */
    public function getUserAgents(): iterable
    {
        foreach ($this->collection as $source) {
            yield from $source->getUserAgents();
        }
    }

    /**
     * @return iterable|string[]
     */
    public function getHeaders(): iterable
    {
        foreach ($this->collection as $source) {
            yield from $source->getHeaders();
        }
    }

    /**
     * @return array[]|iterable
     */
    public function getProperties(): iterable
    {
        foreach ($this->collection as $source) {
            yield from $source->getProperties();
        }
    }
}
