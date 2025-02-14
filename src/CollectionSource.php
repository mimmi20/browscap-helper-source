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

namespace BrowscapHelper\Source;

use LogicException;
use Override;
use RuntimeException;

use function count;

final readonly class CollectionSource implements SourceInterface
{
    /** @var array<SourceInterface> */
    private array $collection;

    /** @throws void */
    public function __construct(SourceInterface ...$collection)
    {
        $this->collection = $collection;
    }

    /** @throws void */
    #[Override]
    public function getName(): string
    {
        return 'collection';
    }

    /**
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        return 0 < count($this->collection);
    }

    /**
     * @return iterable<non-empty-string, non-empty-string>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    #[Override]
    public function getUserAgents(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->collection as $source) {
            yield from $source->getUserAgents($message, $messageLength);
        }
    }

    /**
     * @return iterable<non-empty-string, array<non-empty-string, non-empty-string>>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    #[Override]
    public function getHeaders(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->collection as $source) {
            yield from $source->getHeaders($message, $messageLength);
        }
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<non-empty-string, array{headers: array<non-empty-string, non-empty-string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws SourceException
     */
    #[Override]
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        foreach ($this->collection as $source) {
            yield from $source->getProperties($parentMessage, $messageLength);
        }
    }
}
