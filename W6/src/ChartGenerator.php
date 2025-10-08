<?php
declare(strict_types=1);

use Phplot\Phplot\phplot_truecolor;

class ChartGenerator
{
    private PDO $pdo;
    private string $watermark;

    public function __construct(PDO $pdo, string $watermarkPath)
    {
        $this->pdo = $pdo;
        $this->watermark = $watermarkPath;

        // If watermark is missing, try to create a simple one programmatically
        if (!file_exists($this->watermark) || !is_readable($this->watermark)) {
            if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
                $w = 220;
                $h = 48;
                $img = @imagecreatetruecolor($w, $h);
                if ($img !== false) {
                    // preserve alpha
                    imagesavealpha($img, true);
                    $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
                    imagefill($img, 0, 0, $transparent);

                    $textColor = imagecolorallocatealpha($img, 0, 0, 0, 60); // semi-transparent black
                    // Draw simple text watermark using built-in font
                    imagestring($img, 5, 12, 12, 'WATERMARK', $textColor);

                    // Attempt to save; suppress warnings
                    @imagepng($img, $this->watermark);
                    imagedestroy($img);
                }
            }
        }
    }

    /* ---------- 1. Столбчатая диаграмма: продажи по регионам ---------- */
    public function barByRegion(): string
    {
        $rows = $this->pdo->query(
            "SELECT region, SUM(qty*price) as total FROM sales GROUP BY region"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $plot = $this->buildPlot(800, 400);
        $plot->SetPlotType('bars');
        $plot->SetDataType('text-data');
        $plot->SetTitle('Sales by Region');
        $plot->SetXTitle('Region');
        $plot->SetYTitle('Revenue');
        $plot->SetDataValues($this->formatKeyValueRows($rows));

        $path = $this->renderPlot($plot, 'bar_');

        return $this->applyWatermark($path);
    }

    /* ---------- 2. Круговая: доля товаров по кол-ву ---------- */
    public function pieByProduct(): string
    {
        $rows = $this->pdo->query(
            "SELECT product, SUM(qty) FROM sales
            GROUP BY product ORDER BY SUM DESC LIMIT 10"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $plot = $this->buildPlot(600, 400);
        $plot->SetPlotType('pie');
        $plot->SetDataType('text-data-single');
        $plot->SetShading(0);
        $plot->SetTitle('Top-10 Products by Qty');

        $data = $this->formatKeyValueRows($rows);
        $plot->SetDataValues($data);
        $plot->SetLegend(array_column($data, 0));

        $path = $this->renderPlot($plot, 'pie_');

        return $this->applyWatermark($path);
    }

    /* ---------- 3. Линейный: динамика продаж по месяцам ---------- */
    public function lineByMonth(): string
    {
        $rows = $this->pdo->query(
            "SELECT TO_CHAR(sold_at,'YYYY-MM') as month,
                    SUM(qty*price) as total
            FROM sales
            GROUP BY month ORDER BY month"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $plot = $this->buildPlot(900, 350);
        $plot->SetPlotType('linepoints');
        $plot->SetDataType('text-data');
        $plot->SetTitle('Sales Dynamics');
        $plot->SetXTitle('Month');
        $plot->SetYTitle('Revenue');
        $plot->SetXLabelAngle(45);
        $plot->SetLegend(['Revenue']);
        $plot->SetDataValues($this->formatKeyValueRows($rows));

        $path = $this->renderPlot($plot, 'line_');

        return $this->applyWatermark($path);
    }

    /* ---------- GD: наложение водяного знака ---------- */
    private function applyWatermark(string $imgPath): string
    {
        // If watermark file is missing, just return original image
        if (empty($this->watermark) || !file_exists($this->watermark) || !is_readable($this->watermark)) {
            return $imgPath;
        }

        // Safely get image info
        $info = @getimagesize($imgPath);
        if ($info === false) {
            return $imgPath;
        }

        $ext  = image_type_to_extension($info[2], false);
        $create = 'imagecreatefrom' . $ext;

        // Ensure the helper exists and image creation succeeds
        if (!function_exists($create)) {
            return $imgPath;
        }

    // call the imagecreatefrom* function dynamically
    $dest = @$create($imgPath);
        if ($dest === false) {
            return $imgPath;
        }

        $src = @imagecreatefrompng($this->watermark);
        if ($src === false) {
            imagedestroy($dest);
            return $imgPath;
        }

        // размеры — ensure sizes are valid
        $sx = imagesx($src);
        $sy = imagesy($src);
        $dx = imagesx($dest);
        $dy = imagesy($dest);
        if ($sx === false || $sy === false || $dx === false || $dy === false) {
            imagedestroy($src);
            imagedestroy($dest);
            return $imgPath;
        }

        // полупрозрачность
        @imagefilter($src, IMG_FILTER_COLORIZE, 0, 0, 0, 70); // 70/127 ≈ 55 %

        // позиция – правый-нижний угол
        $x = $dx - $sx - 15;
        $y = $dy - $sy - 15;

        @imagecopy($dest, $src, $x, $y, 0, 0, $sx, $sy);
        imagedestroy($src);

        $outFile = sys_get_temp_dir() . '/wm_' . basename($imgPath);

        $save = 'image' . $ext;
        if (function_exists($save)) {
            @$save($dest, $outFile);
        } else {
            // fallback to PNG
            $outFile = preg_replace('/\.[^.]+$/', '.png', $outFile);
            @imagepng($dest, $outFile);
        }

        imagedestroy($dest);

        return $outFile;
    }

    private function buildPlot(int $width, int $height): phplot_truecolor
    {
        $plot = new phplot_truecolor($width, $height);
        $plot->SetImageBorderType('plain');
        $plot->SetBackgroundColor('white');
        $plot->SetFileFormat('png');
        $plot->SetIsInline(true);

        return $plot;
    }

    /**
     * @param array<string|int, string|int|float> $rows
     * @return array<int, array{0: string, 1: float}>
     */
    private function formatKeyValueRows(array $rows): array
    {
        if (empty($rows)) {
            throw new RuntimeException('No data available to build chart');
        }

        $result = [];
        foreach ($rows as $label => $value) {
            $result[] = [(string)$label, (float)$value];
        }

        return $result;
    }

    private function renderPlot(phplot_truecolor $plot, string $prefix): string
    {
        $path = $this->createTempImagePath($prefix);
        $plot->SetOutputFile($path);
        $plot->DrawGraph();

        return $path;
    }

    private function createTempImagePath(string $prefix): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);
        if ($base === false) {
            throw new RuntimeException('Unable to allocate temporary file for chart');
        }

        $target = $base . '.png';
        if (!@rename($base, $target)) {
            $target = $base;
        }

        return $target;
    }
}