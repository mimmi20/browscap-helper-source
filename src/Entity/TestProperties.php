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

final class TestProperties
{
    /**
     * @param array<non-empty-string, non-empty-string>                                                                                                                                                                                                                                                                                                                                $headers
     * @param array{architecture: string|null, deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, dualOrientation: bool|null, simCount: int|null, display: array{width: int|null, height: int|null, touch: bool|null, size: float|null, type?: string|null}, type: string|null, ismobile: bool|null, istv: bool|null, bits: int|null} $device
     * @param array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}                                                                                                                                                                                                                                                    $os
     * @param array{name: string|null, version: string|null, manufacturer: string|null, type: string|null, bits: int|null, modus: string|null}                                                                                                                                                                                                                                         $client
     * @param array{name: string|null, version: string|null, manufacturer: string|null}                                                                                                                                                                                                                                                                                                $engine
     *
     * @throws void
     */
    public function __construct(
        private readonly array $headers,
        private readonly array $device,
        private readonly array $os,
        private readonly array $client,
        private readonly array $engine,
        private readonly string | null $file,
        private string | null $dateFirst,
        private string | null $dateLast,
        private readonly mixed $raw,
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
     * @return array{architecture: string|null, deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, dualOrientation: bool|null, simCount: int|null, display: array{width: int|null, height: int|null, touch: bool|null, size: float|null, type?: string|null}, type: string|null, ismobile: bool|null, istv: bool|null, bits: int|null}
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
     * @throws void
     *
     * @api
     */
    public function getDateFirst(): string | null
    {
        return $this->dateFirst;
    }

    /**
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
     * @throws void
     *
     * @api
     */
    public function withDateFirst(string $dateFirst): self
    {
        $cloned            = clone $this;
        $cloned->dateFirst = $dateFirst;

        return $cloned;
    }

    /**
     * @throws void
     *
     * @api
     */
    public function withDateLast(string $dateLast): self
    {
        $cloned           = clone $this;
        $cloned->dateLast = $dateLast;

        return $cloned;
    }
}
