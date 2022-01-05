<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2022, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use AppendIterator;
use FilterIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

use function addcslashes;
use function assert;
use function file_exists;
use function file_get_contents;
use function is_array;
use function mb_strlen;
use function sprintf;
use function str_pad;

use const PHP_EOL;
use const STR_PAD_RIGHT;

final class UapCoreSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
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
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{headers: array<non-empty-string, non-empty-string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws RuntimeException
     */
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $agents = [];
        $base   = [
            'headers' => ['user-agent' => null],
            'device' => [
                'deviceName' => null,
                'marketingName' => null,
                'manufacturer' => null,
                'brand' => null,
                'display' => [
                    'width' => null,
                    'height' => null,
                    'touch' => null,
                    'type' => null,
                    'size' => null,
                ],
                'dualOrientation' => null,
                'type' => null,
                'simCount' => null,
                'ismobile' => null,
            ],
            'client' => [
                'name' => null,
                'modus' => null,
                'version' => null,
                'manufacturer' => null,
                'bits' => null,
                'type' => null,
                'isbot' => null,
            ],
            'platform' => [
                'name' => null,
                'marketingName' => null,
                'version' => null,
                'manufacturer' => null,
                'bits' => null,
            ],
            'engine' => [
                'name' => null,
                'version' => null,
                'manufacturer' => null,
            ],
            'raw' => [],
            'file' => [],
        ];

        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERBOSE);

        $appendIter = new AppendIterator();
        $appendIter->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../vendor/ua-parser/uap-core/tests')));
        $appendIter->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../vendor/ua-parser/uap-core/test_resources')));
        $files = new class ($appendIter, 'yaml') extends FilterIterator {
            private string $extension;

            /**
             * @param Iterator<SplFileInfo> $iterator
             */
            public function __construct(Iterator $iterator, string $extension)
            {
                parent::__construct($iterator);
                $this->extension = $extension;
            }

            public function accept(): bool
            {
                $file = $this->getInnerIterator()->current();

                assert($file instanceof \SplFileInfo);

                return $file->isFile() && $file->getExtension() === $this->extension;
            }
        };

        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            $filepath = $file->getPathname();

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERY_VERBOSE);

            $content = file_get_contents($filepath);

            if (false === $content || '' === $content || PHP_EOL === $content) {
                continue;
            }

            $provider = Yaml::parse($content);

            if (!is_array($provider)) {
                continue;
            }

            $providerName = $file->getFilename();

            foreach ($provider['test_cases'] as $data) {
                if (!is_array($data)) {
                    continue;
                }

                $agent = addcslashes($data['user_agent_string'], "\n");

                if ('' === $agent) {
                    continue;
                }

                if (!isset($agents[$agent])) {
                    $agents[$agent] = $base;

                    $agents[$agent]['headers']['user-agent'] = $agent;
                }

                switch ($providerName) {
                    case 'test_device.yaml':
                        $agents[$agent]['device']['name']  = $data['model'] ?? null;
                        $agents[$agent]['device']['brand'] = $data['brand'] ?? null;

                        $agents[$agent]['raw'][$providerName]  = $data;
                        $agents[$agent]['file'][$providerName] = $filepath;

                        break;
                    case 'test_os.yaml':
                    case 'additional_os_tests.yaml':
                        $agents[$agent]['platform']['name']    = $data['family'] ?? null;
                        $agents[$agent]['platform']['version'] = $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : '');

                        $agents[$agent]['raw'][$providerName]  = $data;
                        $agents[$agent]['file'][$providerName] = $filepath;

                        break;
                    case 'test_ua.yaml':
                    case 'firefox_user_agent_strings.yaml':
                    case 'opera_mini_user_agent_strings.yaml':
                    case 'pgts_browser_list.yaml':
                        $agents[$agent]['client']['name']    = $data['family'] ?? null;
                        $agents[$agent]['client']['version'] = $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : '');

                        $agents[$agent]['raw'][$providerName]  = $data;
                        $agents[$agent]['file'][$providerName] = $filepath;

                        break;
                }
            }
        }

        yield from $agents;
    }
}
