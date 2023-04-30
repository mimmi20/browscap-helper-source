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

use LogicException;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use SplFileObject;
use Symfony\Component\Console\Output\OutputInterface;

use function array_unique;
use function count;
use function file_exists;
use function mb_strlen;
use function mb_strpos;
use function preg_match;
use function sprintf;
use function str_pad;
use function str_replace;
use function trim;
use function usort;

use const STR_PAD_RIGHT;

final class ZsxsoftSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'zsxsoft/php-useragent';

    private const PATH = 'vendor/zsxsoft/php-useragent/lib';

    /** @throws void */
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
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $brands = $this->getBrands();

        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERBOSE,
        );

        $filepath = 'vendor/zsxsoft/php-useragent/tests/UserAgentList.php';

        $message = $parentMessage . sprintf('- reading file %s', $filepath);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERY_VERBOSE,
        );

        $provider = include $filepath;

        foreach ($provider as $data) {
            if (!isset($data[0][0])) {
                continue;
            }

            $agent = trim((string) $data[0][0]);

            if ($agent === '') {
                continue;
            }

            $model = null;
            $brand = null;

            foreach ($brands as $brand) {
                if (mb_strpos((string) $data[1][8], $brand) !== false) {
                    $model = trim(str_replace($brand, '', (string) $data[1][8]));

                    break;
                }

                $brand = null;
            }

            $uid = Uuid::uuid4()->toString();

            yield $uid => [
                'client' => [
                    'bits' => null,
                    'isbot' => null,
                    'manufacturer' => null,
                    'modus' => null,
                    'name' => empty($data[1][2]) ? null : $data[1][2],
                    'type' => null,
                    'version' => empty($data[1][3]) ? null : $data[1][3],
                ],
                'device' => [
                    'brand' => empty($brand) ? null : $brand,
                    'deviceName' => empty($model) ? null : $model,
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
                'file' => $filepath,
                'headers' => ['user-agent' => $agent],
                'platform' => [
                    'bits' => null,
                    'manufacturer' => null,
                    'marketingName' => null,
                    'name' => empty($data[1][5]) ? null : $data[1][5],
                    'version' => empty($data[1][6]) ? null : $data[1][6],
                ],
                'raw' => $data,
            ];
        }
    }

    /**
     * @return array<string>
     *
     * @throws SourceException
     */
    private function getBrands(): array
    {
        try {
            $file = new SplFileObject('vendor/zsxsoft/php-useragent/lib/useragent_detect_device.php');
        } catch (LogicException | RuntimeException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }

        $file->setFlags(SplFileObject::DROP_NEW_LINE);
        $brands = [];

        while (!$file->eof()) {
            $line = $file->fgets();

            if ($line === false) {
                continue;
            }

            $line = trim($line);
            preg_match('/^\$brand = (["\'])(.*)(["\']);$/', $line, $matches);

            if (0 >= count($matches)) {
                continue;
            }

            $brand = $matches[2];

            if (empty($brand)) {
                continue;
            }

            $brands[] = $brand;
        }

        $brands = array_unique($brands);

        usort($brands, static fn ($a, $b): int => mb_strlen($b) - mb_strlen($a));

        return $brands;
    }
}
