<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2024, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use AppendIterator;
use FilterIterator;
use Iterator;
use Override;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

use function addcslashes;
use function array_key_exists;
use function assert;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_iterable;
use function is_scalar;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;
use function str_replace;

use const PHP_EOL;
use const STR_PAD_RIGHT;

final class UapCoreSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const string NAME = 'ua-parser/uap-core';

    private const string PATH = 'vendor/ua-parser/uap-core/tests';

    /** @throws void */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        if (file_exists(self::PATH)) {
            return true;
        }

        $this->writeln(
            "\r" . '<error>' . $parentMessage . sprintf('- path %s not found</error>', self::PATH),
            OutputInterface::VERBOSITY_NORMAL,
        );

        return false;
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
        $agents = [];
        $base   = [
            'client' => [
                'bits' => null,
                'isbot' => null,
                'manufacturer' => null,
                'modus' => null,
                'name' => null,
                'type' => null,
                'version' => null,
            ],
            'device' => [
                'brand' => null,
                'deviceName' => null,
                'display' => [
                    'height' => null,
                    'size' => null,
                    'touch' => null,
                    'type' => null,
                    'width' => null,
                ],
                'dualOrientation' => null,
                'ismobile' => null,
                'manufacturer' => null,
                'marketingName' => null,
                'simCount' => null,
                'type' => null,
            ],
            'engine' => [
                'manufacturer' => null,
                'name' => null,
                'version' => null,
            ],
            'file' => [],
            'headers' => ['user-agent' => null],
            'platform' => [
                'bits' => null,
                'manufacturer' => null,
                'marketingName' => null,
                'name' => null,
                'version' => null,
            ],
            'raw' => [],
        ];

        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERBOSE,
        );

        $appendIter = new AppendIterator();

        if (file_exists('vendor/ua-parser/uap-core/tests')) {
            try {
                $appendIter->append(
                    new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator('vendor/ua-parser/uap-core/tests'),
                    ),
                );
            } catch (UnexpectedValueException $e) {
                throw new SourceException($e->getMessage(), 0, $e);
            }
        }

        if (file_exists('vendor/ua-parser/uap-core/test_resources')) {
            try {
                $appendIter->append(
                    new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator('vendor/ua-parser/uap-core/test_resources'),
                    ),
                );
            } catch (UnexpectedValueException $e) {
                throw new SourceException($e->getMessage(), 0, $e);
            }
        }

        $files = new class ($appendIter, 'yaml') extends FilterIterator {
            /**
             * @param Iterator<SplFileInfo> $iterator
             *
             * @throws void
             */
            public function __construct(Iterator $iterator, private readonly string $extension)
            {
                parent::__construct($iterator);
            }

            /** @throws void */
            #[Override]
            public function accept(): bool
            {
                $file = $this->getInnerIterator()->current();

                assert($file instanceof SplFileInfo);

                return $file->isFile() && $file->getExtension() === $this->extension;
            }
        };

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            assert($file instanceof SplFileInfo);
            $filepath = $file->getPathname();
            $filepath = str_replace('\\', '/', $filepath);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write(
                "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
                false,
                OutputInterface::VERBOSITY_VERY_VERBOSE,
            );

            $content = file_get_contents($filepath);

            if ($content === false || $content === '' || $content === PHP_EOL) {
                continue;
            }

            try {
                $provider = Yaml::parse($content);
            } catch (ParseException $e) {
                throw new SourceException($e->getMessage(), 0, $e);
            }

            if (
                !is_array($provider)
                || !array_key_exists('test_cases', $provider)
                || !is_iterable($provider['test_cases'])
            ) {
                continue;
            }

            $providerName = $file->getFilename();

            foreach ($provider['test_cases'] as $data) {
                if (!is_array($data)) {
                    continue;
                }

                assert(is_scalar($data['user_agent_string']));

                $agent = addcslashes((string) $data['user_agent_string'], "\n");

                if ($agent === '') {
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

        foreach ($agents as $agent) {
            $uid = Uuid::uuid4()->toString();

            yield $uid => $agent;
        }
    }
}
