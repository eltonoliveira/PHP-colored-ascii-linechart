<?php
declare(strict_types = 1);

namespace noximo\PHPColoredAsciiLinechart;

use noximo\PHPColoredAsciiLinechart\Colorizers\IColorizer;

/**
 * Class LineGraph
 * @package noximo\PHPColoredConsoleLinegraph
 */
class Linechart
{
    /**
     * @var string
     */
    public const CROSS = 'cross';

    /**
     * @var string
     */
    public const POINT = 'point';

    /**
     * @var string
     */
    public const DASHED_LINE = 'dashedLine';

    /**
     * @var string
     */
    public const FULL_LINE = 'fullLIne';
    /**
     * @var Settings
     */
    private $settings;
    /**
     * @var array $allmarkers = [
     * [['markers' => [1,2,3.45], 'colors' => [1,2,3]]];
     * ]
     */
    private $allmarkers = [];
    /**
     * @var int
     */
    private $allTimeMaxHeight = 0;
    /**
     * @var array
     */
    private $currentColors;

    private $width;
    /**
     * @var int
     */
    private $count;
    /**
     * @var int
     */
    private $range;
    /**
     * @var float
     */
    private $ratio;
    /**
     * @var int
     */
    private $min2;
    /**
     * @var int
     */
    private $max2;
    /**
     * @var int
     */
    private $rows;
    /**
     * @var int
     */
    private $offset;
    /**
     * @var IColorizer
     */
    private $colorizer;

    /**
     * @param int $x alias x coordinate
     * @param float $y alias y coordinate
     * @param array $colors
     * @param string|null $appearance
     *
     * @return Linechart
     */
    public function addPoint(int $x, float $y, array $colors = null, string $appearance = null): Linechart
    {
        $markers[0] = $y;
        $markers[$x] = $y;
        if (!\in_array($appearance, [self::CROSS, self::POINT], true)) {
            $appearance = self::POINT;
        }
        $this->addMarkerData($markers, $colors, null, $appearance);

        return $this;
    }

    /**
     * @param array $markers
     * @param array $colors
     * @param array|null $colorsDown
     * @param string|null $point
     *
     * @return Linechart
     */
    private function addMarkerData(array $markers, array $colors = null, array $colorsDown = null, string $point = null): Linechart
    {
        $markersData = [
            'markers' => $this->normalizeData($markers),
            'colors' => $colors ?? [],
            'colorsDown' => $colorsDown ?? $colors ?? [],
            'point' => $point,
        ];

        $this->allmarkers[] = $markersData;

        return $this;
    }

    /**
     * @param array $markers
     *
     * @return array
     */
    private function normalizeData(array $markers): array
    {
        $markers = array_filter($markers, '\is_int', ARRAY_FILTER_USE_KEY);
        ksort($markers);

        return $markers;
    }

    /**
     * @param array $markers
     * @param array $colors
     * @param array|null $colorsDown
     *
     * @return Linechart
     */
    public function addMarkers(array $markers, array $colors = null, array $colorsDown = null): Linechart
    {
        $this->addMarkerData($markers, $colors, $colorsDown);

        return $this;
    }

    /**
     * @param float $value alias y coordinate
     * @param array $colors
     * @param string|null $appearance
     *
     * @return Linechart
     */
    public function addLine(float $value, array $colors = null, string $appearance = null): Linechart
    {
        $markers[0] = $value;
        if (!\in_array($appearance, [self::DASHED_LINE, self::FULL_LINE], true)) {
            $appearance = self::DASHED_LINE;
        }
        $this->addMarkerData($markers, $colors, null, $appearance);

        return $this;
    }

    /**
     * @return Chart
     */
    public function chart(): Chart
    {
        $graph = $this->prepareData();

        foreach ($this->allmarkers as $markersData) {
            $this->currentColors = $this->currentColors ?? $markersData['colors'];
            $result = $this->prepareResult();

            $result = $this->processBorder($result, $markersData, $graph);
            $isPoint = \in_array($markersData['point'], [self::CROSS, self::POINT], true);
            $isLine = \in_array($markersData['point'], [self::DASHED_LINE, self::FULL_LINE], true);

            foreach ($markersData['markers'] as $x => $value) {
                $y0 = (int) round($value * $this->ratio) - $this->min2;

                if ($this->isPresent($markersData['markers'], $x + 1)) {
                    $result = $this->processLinearGraph($result, $markersData, $x, $y0);
                } elseif ($x !== 0 && $isPoint) {
                    $result = $this->processPoint($result, $markersData, $y0, $x);
                } elseif ($x === 0 && $isLine) {
                    $result = $this->processLine($result, $y0, $markersData['point']);
                }
            }

            $this->currentColors = null;
            $graph->addResult($result);
        }

        return $graph;
    }

    /**
     * @return Chart
     */
    private function prepareData(): Chart
    {
        $graph = new Chart();
        $graph->setSettings($this->getSettings());

        $this->colorizer = $this->getSettings()->getColorizer();
        $this->findMinMax($graph, $this->allmarkers);

        $this->width = $graph->getWidth();
        $this->count = $graph->getWidth();

        $this->range = (int) max(1, abs($graph->getMax() - $graph->getMin()));
        $this->getSettings()->setComputedHeight($this->range);

        $graph->setAlltimeMaxHeight($this->allTimeMaxHeight);

        $this->ratio = $this->getSettings()->getHeight() / $this->range;
        $this->min2 = (int) round($graph->getMin() * $this->ratio);
        $this->max2 = (int) round($graph->getMax() * $this->ratio);

        $this->rows = max(1, abs($this->max2 - $this->min2));

        $this->allTimeMaxHeight = max($this->allTimeMaxHeight, $this->rows);
        $this->offset = $this->getSettings()->getOffset();
        $this->width += $this->offset;

        return $graph;
    }

    /**
     * @return Settings
     */
    public function getSettings(): Settings
    {
        if ($this->settings === null) {
            $this->settings = new Settings();
        }

        return $this->settings;
    }

    /**
     * @param Settings $settings
     *
     * @return Linechart
     */
    public function setSettings(Settings $settings): Linechart
    {
        $this->settings = $settings;

        return $this;
    }

    /**
     * @param Chart $graph
     * @param array $allmarkers
     */
    private function findMinMax(Chart $graph, array $allmarkers): void
    {
        $width = 0;
        $min = PHP_INT_MAX;
        $max = -PHP_INT_MAX;
        foreach ($allmarkers as $markers) {
            end($markers['markers']);
            $width = max($width, key($markers['markers']));

            /** @var int[][] $markers */
            foreach ($markers['markers'] as $value) {
                if ($value !== null && $value !== false) {
                    $min = min($min, $value);
                    $max = max($max, $value);
                }
            }
        }

        $graph->setMax($max);
        $graph->setMin($min);
        $graph->setWidth($width);
    }

    /**
     * @return array
     */
    private function prepareResult(): array
    {
        $result = [];

        /** @noinspection ForeachInvariantsInspection */
        for ($i = 0; $i <= $this->rows; $i++) {
            $result[$i] = array_fill(0, $this->width, ' ');
        }

        return $result;
    }

    /**
     * @param array $result
     * @param array $markersData
     * @param Chart $graph
     *
     * @return array
     */
    private function processBorder(array $result, array $markersData, Chart $graph): array
    {
        $format = $this->getSettings()->getFormat();
        $y0 = (int) round($markersData['markers'][0] * $this->ratio) - $this->min2;
        for ($y = $this->min2; $y <= $this->max2; ++$y) {
            $rawLabel = $graph->getMax() - ($y - $this->min2) * $this->range / $this->rows;
            $label = $format($rawLabel, $this->getSettings());

            $border = '┤';
            if ($y - $this->min2 === $this->rows - $y0) {
                $label = $this->colorizer->colorize($label, $this->currentColors);
                $border = $this->colorizer->colorize('┼', $this->currentColors);
            }

            $result[$y - $this->min2][max($this->offset - \strlen($label), 0)] = $label;
            $result[$y - $this->min2][$this->offset - 1] = $border;
        }

        return $result;
    }

    /**
     * @param array $markers
     * @param int $x
     *
     * @return bool
     */
    private function isPresent(array $markers, int $x): bool
    {
        return isset($markers[$x]) && ($markers[$x] !== null || $markers[$x] !== false);
    }

    /**
     * @param array $result
     * @param array $markersData
     * @param int $x
     * @param int $y
     *
     * @return array
     */
    private function processLinearGraph(array $result, array $markersData, int $x, int $y): array
    {
        $y1 = (int) round($markersData['markers'][$x + 1] * $this->ratio) - $this->min2;
        if ($y === $y1) {
            $result[$this->rows - $y][$x + $this->offset] = $this->colorizer->colorize('─', $this->currentColors);
        } else {
            if ($y > $y1) {
                $connectA = '╰';
                $connectB = '╮';

                $this->currentColors = $markersData['colorsDown'];
            } else {
                $connectA = '╭';
                $connectB = '╯';

                $this->currentColors = $markersData['colors'];
            }
            $result[$this->rows - $y1][$x + $this->offset] = $this->colorizer->colorize($connectA, $this->currentColors);
            $result[$this->rows - $y][$x + $this->offset] = $this->colorizer->colorize($connectB, $this->currentColors);

            $from = min($y, $y1);
            $to = max($y, $y1);
            for ($i = $from + 1; $i < $to; $i++) {
                $result[$this->rows - $i][$x + $this->offset] = $this->colorizer->colorize('│', $this->currentColors);
            }
        }

        return $result;
    }

    /**
     * @param array $result
     * @param array $markersData
     * @param int $y
     * @param int $x
     *
     * @return array
     */
    private function processPoint(array $result, array $markersData, int $y, int $x): array
    {
        if ($markersData['point'] === self::CROSS) {
            for ($i = 0; $i <= $this->width - $this->offset - 2; $i++) {
                $result[$this->rows - $y][$i + $this->offset] = $this->colorizer->colorize('╌', $this->currentColors);
            }
            for ($i = 0; $i <= $this->rows; $i++) {
                $result[$this->rows - $i][$x + $this->offset] = $this->colorizer->colorize('╎', $this->currentColors);
            }
        }

        $result[$this->rows - $y][$x + $this->offset] = $this->colorizer->colorize('o', $this->currentColors);

        return $result;
    }

    /**
     * @param array $result
     * @param int $y
     * @param string $lineStyle
     *
     * @return array
     */
    private function processLine(array $result, int $y, string $lineStyle): array
    {
        $line = '╌';
        if ($lineStyle === self::FULL_LINE) {
            $line = '─';
        }

        for ($i = 0; $i <= $this->width - $this->offset - 2; $i++) {
            $result[$this->rows - $y][$i + $this->offset] = $this->colorizer->colorize($line, $this->currentColors);
        }

        return $result;
    }

    /**
     * @return Linechart
     */
    public function clearAllMarkers(): Linechart
    {
        $this->allmarkers = [];

        return $this;
    }
}
