<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Phyxo\Image;

use App\ImageLibraryGuesser;
use Phyxo\Image\ImageOptimizer;
use Imagine\Image\ImagineInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ImageOptimizerTest extends TestCase
{
    private string $fixtures_dir = __DIR__ . '/../../fixtures/media';
    private string $media_cache_dir = __DIR__ . '/../../media';
    private string $img1 = 'img1.jpg';
    private ImagineInterface $library;

    protected function setUp(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->media_cache_dir);
        $fs->mkdir($this->media_cache_dir);

        $imageLibraryGuesser = new ImageLibraryGuesser('auto');
        $this->library = $imageLibraryGuesser->getLibrary();
    }

    public function testWidthAndHeight(): void
    {
        $image_path = sprintf('%s/%s', $this->fixtures_dir, $this->img1);

        $imageOptimizer = new ImageOptimizer($image_path, $this->library);
        $this->assertEquals(4000, $imageOptimizer->getWidth());
        $this->assertEquals(2248, $imageOptimizer->getHeight());
    }

    public function testRotate(): void
    {
        $image_path = sprintf('%s/%s', $this->fixtures_dir, $this->img1);

        $imageOptimizer = new ImageOptimizer($image_path, $this->library);
        $this->assertEquals(90, $imageOptimizer->getRotationAngle());

        if ($imageOptimizer->getRotationAngle() !== 0) {
            $imageOptimizer->rotate($imageOptimizer->getRotationAngle());
        }

        $result_path = sprintf('%s/%s', $this->media_cache_dir, $this->img1);
        $imageOptimizer->write($result_path);

        $imageOptimizer = new ImageOptimizer($result_path, $this->library);
        $this->assertEquals(2248, $imageOptimizer->getWidth());
        $this->assertEquals(4000, $imageOptimizer->getHeight());
    }

    public function testMainResizeWithAutorotate(): void
    {
        $image_path = sprintf('%s/%s', $this->fixtures_dir, $this->img1);
        $result_path = sprintf('%s/%s', $this->media_cache_dir, $this->img1);

        $imageOptimizer = new ImageOptimizer($image_path, $this->library);
        $imageOptimizer->mainResize($result_path, 2000, 2000, 95, true);

        $imageOptimizer = new ImageOptimizer($result_path, $this->library);
        $this->assertEquals(1124, $imageOptimizer->getWidth());
        $this->assertEquals(2000, $imageOptimizer->getHeight());
    }
}
