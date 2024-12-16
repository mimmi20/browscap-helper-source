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

use FilterIterator;
use Iterator;
use Override;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

use function array_key_exists;
use function assert;
use function file_exists;
use function in_array;
use function is_array;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;
use function str_replace;
use function trim;

use const STR_PAD_RIGHT;

final class BrowscapSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const string NAME = 'browscap/browscap';

    private const string PATH = 'vendor/browscap/browscap/tests/issues';

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
        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERBOSE,
        );

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PATH));
        } catch (UnexpectedValueException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }

        $files = new class ($iterator, 'php') extends FilterIterator {
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
            assert($file instanceof SplFileInfo);

            if (
                in_array(
                    $file->getFilename(),
                    ['issue-000-invalids.php', 'issue-000-invalid-versions.php'],
                    true,
                )
            ) {
                continue;
            }

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

            $data = include $filepath;

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                if (!array_key_exists('ua', $row)) {
                    continue;
                }

                $agent = trim((string) $row['ua']);

                if ($agent === '') {
                    continue;
                }

                $pointingMethod = $row['properties']['Device_Pointing_Method'] ?? null;

                $isMobile = false;

                if (isset($row['properties']['Device_Type'])) {
                    switch ($row['properties']['Device_Type']) {
                        case 'Mobile Phone':
                        case 'Tablet':
                        case 'Console':
                        case 'Digital Camera':
                        case 'Ebook Reader':
                        case 'Mobile Device':
                            $isMobile = true;

                            break;
                    }
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => [
                    'client' => [
                        'bits' => $row['properties']['Browser_Bits'] ?? null,
                        'isbot' => array_key_exists(
                            'Crawler',
                            $row['properties'],
                        ) ? $row['properties']['Crawler'] : null,
                        'manufacturer' => $row['properties']['Browser_Maker'] ?? null,
                        'modus' => $row['properties']['Browser_Modus'] ?? null,
                        'name' => $row['properties']['Browser'] ?? null,
                        'type' => $row['properties']['Browser_Type'] ?? null,
                        'version' => $row['properties']['Version'] ?? null,
                    ],
                    'device' => [
                        'brand' => $row['properties']['Device_Brand_Name'] ?? null,
                        'deviceName' => $row['properties']['Device_Code_Name'] ?? null,
                        'display' => [
                            'height' => null,
                            'size' => null,
                            'touch' => ($pointingMethod === 'touchscreen'),
                            'type' => null,
                            'width' => null,
                        ],
                        'dualOrientation' => null,
                        'ismobile' => $isMobile,
                        'manufacturer' => $row['properties']['Device_Maker'] ?? null,
                        'marketingName' => $row['properties']['Device_Name'] ?? null,
                        'simCount' => null,
                        'type' => $row['properties']['Device_Type'] ?? null,
                    ],
                    'engine' => [
                        'manufacturer' => $row['properties']['RenderingEngine_Maker'] ?? null,
                        'name' => $row['properties']['RenderingEngine_Name'] ?? null,
                        'version' => $row['properties']['RenderingEngine_Version'] ?? null,
                    ],
                    'file' => $filepath,
                    'headers' => ['user-agent' => $agent],
                    'platform' => [
                        'bits' => $row['properties']['Platform_Bits'] ?? null,
                        'manufacturer' => $row['properties']['Platform_Maker'] ?? null,
                        'marketingName' => null,
                        'name' => $row['properties']['Platform'] ?? null,
                        'version' => $row['properties']['Platform_Version'] ?? null,
                    ],
                    'raw' => $row,
                ];
            }
        }
    }
}
