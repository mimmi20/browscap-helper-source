<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2026, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source\Entity;

use function array_merge_recursive;

final readonly class TestProperties
{
    /**
     * @param array<non-empty-string, non-empty-string>                                                                                                                                                                                                                                                                                          $headers
     * @param array{architecture: string|null, deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, dualOrientation: bool|null, simCount: int|null, display: array{width: int|null, height: int|null, touch: bool|null, size: float|null, type?: string|null}, type: string|null, bits: int|null} $device
     * @param array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}                                                                                                                                                                                                              $os
     * @param array{name: string|null, version: string|null, manufacturer: string|null, type: string|null, bits: int|null, modus: string|null}                                                                                                                                                                                                   $client
     * @param array{name: string|null, version: string|null, manufacturer: string|null}                                                                                                                                                                                                                                                          $engine
     * @param non-empty-string|null                                                                                                                                                                                                                                                                                                              $dateFirst
     * @param non-empty-string|null                                                                                                                                                                                                                                                                                                              $dateLast
     * @param array<non-empty-string, array{date-first: non-empty-string|null, date-last: non-empty-string|null}>                                                                                                                                                                                                                                $sources
     *
     * @throws void
     */
    public function __construct(
        private array $headers,
        private array $device,
        private array $os,
        private array $client,
        private array $engine,
        private string | null $file,
        private string | null $dateFirst,
        private string | null $dateLast,
        private mixed $raw,
        private array $sources,
    ) {
        // nothing to do
    }

    /**
     * @return array<non-empty-string, non-empty-string>
     *
     * @throws void
     *
     * @api
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array{architecture: string|null, deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, dualOrientation: bool|null, simCount: int|null, display: array{width: int|null, height: int|null, touch: bool|null, size: float|null, type?: string|null}, type: string|null, bits: int|null}
     *
     * @throws void
     *
     * @api
     */
    public function getDevice(): array
    {
        return $this->device;
    }

    /**
     * @return array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}
     *
     * @throws void
     *
     * @api
     */
    public function getOs(): array
    {
        return $this->os;
    }

    /**
     * @return array{name: string|null, version: string|null, manufacturer: string|null, type: string|null, bits: int|null, modus: string|null}
     *
     * @throws void
     *
     * @api
     */
    public function getClient(): array
    {
        return $this->client;
    }

    /**
     * @return array{name: string|null, version: string|null, manufacturer: string|null}
     *
     * @throws void
     *
     * @api
     */
    public function getEngine(): array
    {
        return $this->engine;
    }

    /**
     * @return non-empty-string|null
     *
     * @throws void
     *
     * @api
     */
    public function getDateFirst(): string | null
    {
        return $this->dateFirst;
    }

    /**
     * @return non-empty-string|null
     *
     * @throws void
     *
     * @api
     */
    public function getDateLast(): string | null
    {
        return $this->dateLast;
    }

    /**
     * @throws void
     *
     * @api
     */
    public function getFile(): string | null
    {
        return $this->file;
    }

    /**
     * @throws void
     *
     * @api
     */
    public function getRaw(): mixed
    {
        return $this->raw;
    }

    /**
     * @return array<non-empty-string, array{date-first: non-empty-string|null, date-last: non-empty-string|null}>
     *
     * @throws void
     *
     * @api
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * @param non-empty-string $dateFirst
     *
     * @throws void
     *
     * @api
     */
    public function withDateFirst(string $dateFirst): self
    {
        return clone ($this, ['dateFirst' => $dateFirst]);
    }

    /**
     * @param non-empty-string $dateLast
     *
     * @throws void
     *
     * @api
     */
    public function withDateLast(string $dateLast): self
    {
        return clone ($this, ['dateLast' => $dateLast]);
    }

    /**
     * @param array<non-empty-string, array{date-first: non-empty-string|null, date-last: non-empty-string|null}> $sources
     *
     * @throws void
     *
     * @api
     */
    public function withSources(array $sources): self
    {
        return clone ($this, ['sources' => array_merge_recursive($this->sources, $sources)]);
    }
}
