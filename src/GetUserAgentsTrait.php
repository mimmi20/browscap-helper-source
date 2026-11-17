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

namespace BrowscapHelper\Source;

use BrowserDetector\Loader\CompanyLoaderFactory;
use BrowserDetector\Version\Exception\NotNumericException;
use BrowserDetector\Version\NullVersion;
use BrowserDetector\Version\VersionBuilder;
use Override;
use Psr\Log\LoggerInterface;
use UaBrowserType\Type as ClientType;
use UaData\Company;
use UaDeviceType\Type as DeviceType;
use UaLoader\Exception\NotFoundException;
use UaResult\Bits\Bits;
use UaResult\Browser\Browser;
use UaResult\Device\Architecture;
use UaResult\Device\Device;
use UaResult\Device\Display;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;

use function assert;
use function is_string;

trait GetUserAgentsTrait
{
    /**
     * @return iterable<non-empty-string, array<non-empty-string, non-empty-string>>
     *
     * @throws Exception\SourceException
     */
    #[Override]
    public function getHeaders(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->getProperties($message, $messageLength) as $uid => $property) {
            assert(is_string($uid));

            yield $uid => $property['headers'];
        }
    }

    /**
     * @param array{device: array{architecture: string, deviceName: string|null, marketingName: string|null, manufacturer: string, brand: string, type: string, ismobile: bool, istv: bool, display: array{width: int|null, height: int|null, touch: bool|null, size: float|null}, dualOrientation: bool|null, simCount: int|null, bits: int}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string, type: string, isbot: bool, bits: int}, os: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string, bits: int}, engine: array{name: string|null, version: string|null, manufacturer: string}, date-first?: string, date-last?: string} $data
     * @param array<non-empty-string, non-empty-string>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        $headers
     *
     * @throws void
     */
    private function buildResult(LoggerInterface $consoleLogger, array $data, array $headers): Result
    {
        $versionBuilder = new VersionBuilder();

        $companyLoaderFactory = new CompanyLoaderFactory(logger: $consoleLogger);
        $companyLoader        = $companyLoaderFactory();

        try {
            $osVersion = $versionBuilder->set($data['os']['version'] ?? '');
        } catch (NotNumericException) {
            $osVersion = new NullVersion();
        }

        try {
            $clientVersion = $versionBuilder->set($data['client']['version'] ?? '');
        } catch (NotNumericException) {
            $clientVersion = new NullVersion();
        }

        try {
            $engineVersion = $versionBuilder->set($data['engine']['version'] ?? '');
        } catch (NotNumericException) {
            $engineVersion = new NullVersion();
        }

        try {
            $deviceManufacturer = $companyLoader->load($data['device']['manufacturer'] ?? '');
        } catch (NotFoundException) {
            $deviceManufacturer = Company::unknown;
        }

        try {
            $deviceBrand = $companyLoader->load($data['device']['brand'] ?? '');
        } catch (NotFoundException) {
            $deviceBrand = Company::unknown;
        }

        try {
            $osManufacturer = $companyLoader->load($data['os']['manufacturer'] ?? '');
        } catch (NotFoundException) {
            $osManufacturer = Company::unknown;
        }

        try {
            $clientManufacturer = $companyLoader->load($data['client']['manufacturer'] ?? '');
        } catch (NotFoundException) {
            $clientManufacturer = Company::unknown;
        }

        try {
            $engineManufacturer = $companyLoader->load($data['engine']['manufacturer'] ?? '');
        } catch (NotFoundException) {
            $engineManufacturer = Company::unknown;
        }

        return new Result(
            headers: $headers,
            device: new Device(
                architecture: Architecture::tryFrom($data['device']['architecture'])
                    ?? Architecture::unknown,
                deviceName: $data['device']['deviceName'] ?? null,
                marketingName: $data['device']['marketingName'] ?? null,
                manufacturer: $deviceManufacturer,
                brand: $deviceBrand,
                type: DeviceType::fromName($data['device']['type'] ?? null),
                display: new Display(
                    width: $data['device']['display']['width'] ?? null,
                    height: $data['device']['display']['height'] ?? null,
                    touch: $data['device']['display']['touch'] ?? null,
                    size: $data['device']['display']['size'] ?? null,
                ),
                dualOrientation: $data['device']['dualOrientation'] ?? null,
                simCount: $data['device']['simCount'] ?? null,
                bits: Bits::tryFrom($data['device']['bits'] ?? 0) ?? Bits::unknown,
                isMobile: $data['device']['isMobile'] ?? null,
            ),
            os: new Os(
                name: $data['os']['name'] ?? null,
                marketingName: $data['os']['marketingName'] ?? null,
                manufacturer: $osManufacturer,
                version: $osVersion,
                bits: Bits::tryFrom($data['os']['bits'] ?? 0) ?? Bits::unknown,
            ),
            browser: new Browser(
                name: $data['client']['name'] ?? null,
                manufacturer: $clientManufacturer,
                version: $clientVersion,
                type: ClientType::fromName($data['client']['type'] ?? null),
                bits: Bits::tryFrom($data['client']['bits'] ?? 0) ?? Bits::unknown,
                modus: $data['client']['modus'] ?? null,
            ),
            engine: new Engine(
                name: $data['engine']['name'] ?? null,
                manufacturer: $engineManufacturer,
                version: $engineVersion,
            ),
        );
    }
}
