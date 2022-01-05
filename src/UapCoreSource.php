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

use BrowscapHelper\Source\Ua\UserAgent;
use LogicException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

use function addcslashes;
use function file_exists;
use function mb_strlen;
use function sprintf;
use function str_pad;

use const STR_PAD_RIGHT;

final class UapCoreSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use OutputAwareTrait;

    private const NAME = 'ua-parser/uap-core';
    private const PATH = 'vendor/ua-parser/uap-core/tests';

    /**
     * @throws void
     */
    public function isReady(string $parentMessage): bool
    {
        if (file_exists(self::PATH)) {
            return true;
        }

        $this->writeln("\r" . '<error>' . $parentMessage . sprintf('- path %s not found</error>', self::PATH), OutputInterface::VERBOSITY_NORMAL);

        return false;
    }

    /**
     * @return iterable<array<string, string>>
     *
     * @throws RuntimeException
     */
    public function getHeaders(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->loadFromPath($message, $messageLength) as $data) {
            $ua    = UserAgent::fromUseragent(addcslashes($data['user_agent_string'], "\n"));
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $ua->getHeaders();
        }
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{headers: array<string, string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getProperties(string $message, int &$messageLength = 0): iterable
    {
        $tests = [];

        foreach ($this->loadFromPath($message, $messageLength) as $providerName => $data) {
            $ua = addcslashes($data['user_agent_string'], "\n");
            if (empty($ua)) {
                continue;
            }

            if (isset($tests[$ua])) {
                $browser  = $tests[$ua]['browser'];
                $platform = $tests[$ua]['platform'];
                $device   = $tests[$ua]['device'];
                $engine   = $tests[$ua]['engine'];
            } else {
                $browser = [
                    'name' => null,
                    'modus' => null,
                    'version' => null,
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ];

                $platform = [
                    'name' => null,
                    'marketingName' => null,
                    'version' => null,
                    'manufacturer' => null,
                    'bits' => null,
                ];

                $device = [
                    'deviceName' => null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => null,
                    'pointingMethod' => null,
                    'resolutionWidth' => null,
                    'resolutionHeight' => null,
                    'type' => null,
                    'ismobile' => null,
                ];

                $engine = [
                    'name' => null,
                    'version' => null,
                    'manufacturer' => null,
                ];
            }

            switch ($providerName) {
                case 'test_device.yaml':
                    $device = [
                        'deviceName' => $data['model'],
                        'marketingName' => null,
                        'manufacturer' => null,
                        'brand' => $data['brand'],
                        'pointingMethod' => null,
                        'resolutionWidth' => null,
                        'resolutionHeight' => null,
                        'type' => null,
                        'ismobile' => null,
                    ];

                    break;
                case 'test_os.yaml':
                case 'additional_os_tests.yaml':
                    $platform = [
                        'name' => $data['family'],
                        'marketingName' => null,
                        'version' => $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : ''),
                        'manufacturer' => null,
                        'bits' => null,
                    ];

                    break;
                case 'test_ua.yaml':
                case 'firefox_user_agent_strings.yaml':
                case 'opera_mini_user_agent_strings.yaml':
                case 'pgts_browser_list.yaml':
                    $browser = [
                        'name' => $data['family'],
                        'modus' => null,
                        'version' => $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : ''),
                        'manufacturer' => null,
                        'bits' => null,
                        'type' => null,
                        'isbot' => null,
                    ];

                    break;
            }

            $tests[$ua] = [
                'headers' => ['user-agent' => $ua],
                'browser' => $browser,
                'platform' => $platform,
                'device' => $device,
                'engine' => $engine,
            ];
        }

        foreach ($tests as $agent => $test) {
            $ua    = UserAgent::fromUseragent($agent);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $agent => $test;
        }
    }

    /**
     * @return iterable<array<string, string>>
     *
     * @throws RuntimeException
     */
    private function loadFromPath(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERBOSE);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.yaml');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in(self::PATH);

        if (file_exists('vendor/ua-parser/uap-core/test_resources')) {
            $finder->in('vendor/ua-parser/uap-core/test_resources');
        }

        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $filepath = $file->getPathname();

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERY_VERBOSE);

            $provider     = Yaml::parse($file->getContents());
            $providerName = $file->getFilename();

            foreach ($provider['test_cases'] as $data) {
                yield $providerName => $data;
            }
        }
    }
}
