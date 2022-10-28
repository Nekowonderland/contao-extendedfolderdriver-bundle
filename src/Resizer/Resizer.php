<?php

namespace Nekowonderland\ExtendedFolderDriver\Resizer;

use Contao\Image\Image;
use Contao\Image\ImageDimensions;
use Contao\Image\ImageInterface;
use Contao\Image\ResizeCalculator;
use Contao\Image\ResizeCalculatorInterface;
use Contao\Image\ResizeConfiguration;
use Contao\Image\ResizeConfigurationInterface;
use Contao\Image\ResizeCoordinates;
use Contao\Image\ResizeCoordinatesInterface;
use Contao\Image\ResizeOptions;
use Contao\Image\ResizeOptionsInterface;
use Contao\Image\ResizerInterface;
use Imagine\Exception\RuntimeException as ImagineRuntimeException;
use Imagine\Image\Palette\RGB;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Resizer implements ResizerInterface
{
    /**
     * @var ResizeCalculatorInterface
     */
    private $calculator;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @param string                         $cacheDir
     * @param ResizeCalculatorInterface|null $calculator
     * @param Filesystem|null                $filesystem
     */
    public function __construct($cacheDir, ResizeCalculator $calculator = null, Filesystem $filesystem = null)
    {
        if (null === $calculator) {
            $calculator = new ResizeCalculator();
        }

        if (null === $filesystem) {
            $filesystem = new Filesystem();
        }

        $this->cacheDir   = (string)$cacheDir;
        $this->calculator = $calculator;
        $this->filesystem = $filesystem;
    }

    /**
     * @param ImageInterface $image
     * @param ResizeOptions  $options
     *
     * @return bool
     */
    private function canSkipResize(ImageInterface $image, ResizeOptions $options): bool
    {
        if (!$options->getSkipIfDimensionsMatch()) {
            return false;
        }

        if (ImageDimensions::ORIENTATION_NORMAL !== $image->getDimensions()->getOrientation()) {
            return false;
        }

        if (
            isset($options->getImagineOptions()['format'])
            && $options->getImagineOptions()['format'] !== strtolower(pathinfo($image->getPath(), PATHINFO_EXTENSION))
        ) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function resize(ImageInterface $image, ResizeConfiguration $config, ResizeOptions $options): ImageInterface
    {
        if (
            $image->getDimensions()->isUndefined()
            || ($config->isEmpty() && $this->canSkipResize($image, $options))
        ) {
            $image = $this->createImage($image, $image->getPath());
        } else {
            $image = $this->processResize($image, $config, $options);
        }

        if (null !== $options->getTargetPath()) {
            $this->filesystem->copy($image->getPath(), $options->getTargetPath(), true);
            $image = $this->createImage($image, $options->getTargetPath());
        }

        return $image;
    }

    /**
     * Executes the resize operation via Imagine.
     *
     * @param ImageInterface    $image
     * @param ResizeCoordinates $coordinates
     * @param string            $path
     * @param ResizeOptions     $options
     *
     * @return ImageInterface
     *
     * @internal Do not call this method in your code; it will be made private in a future version
     */
    protected function executeResize(
        ImageInterface $image,
        ResizeCoordinates $coordinates,
        $path,
        ResizeOptions $options
    ) {
        $dir = \dirname($path);

        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $imagineOptions = $options->getImagineOptions();

        $imagineImage = $image
            ->getImagine()
            ->open($image->getPath())
            ->resize($coordinates->getSize())
            ->crop($coordinates->getCropStart(), $coordinates->getCropSize())
            ->usePalette(new RGB())
            ->strip();

        if (isset($imagineOptions['interlace'])) {
            try {
                $imagineImage->interlace($imagineOptions['interlace']);
            } catch (ImagineRuntimeException $e) {
                // Ignore failed interlacing
            }
        }

        if (!isset($imagineOptions['format'])) {
            $imagineOptions['format'] = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }

        // Atomic write operation
        $tmpPath = $this->filesystem->tempnam($dir, 'img');
        $this->filesystem->chmod($tmpPath, 0666, umask());
        $imagineImage->save($tmpPath, $imagineOptions);
        $this->filesystem->rename($tmpPath, $path, true);

        return $this->createImage($image, $path);
    }

    /**
     * Creates a new image instance for the specified path.
     *
     * @param ImageInterface $image
     * @param string         $path
     *
     * @return ImageInterface
     *
     * @internal Do not call this method in your code; it will be made private in a future version
     */
    protected function createImage(ImageInterface $image, $path)
    {
        return new Image($path, $image->getImagine(), $this->filesystem);
    }

    /**
     * Processes the resize and executes it if not already cached.
     *
     * @param ImageInterface      $image
     * @param ResizeConfiguration $config
     * @param ResizeOptions       $options
     *
     * @return \Contao\Image\ImageInterface|null
     */
    private function processResize(
        ImageInterface $image,
        ResizeConfiguration $config,
        ResizeOptions $options
    ) {
        $coordinates = $this->calculator->calculate($config, $image->getDimensions(), $image->getImportantPart());

        // Skip resizing if it would have no effect
        if (
            $this->canSkipResize($image, $options)
            && !$image->getDimensions()->isRelative()
            && $coordinates->isEqualTo($image->getDimensions()->getSize())
        ) {
            return $this->createImage($image, $image->getPath());
        }

        $cachePath = \Symfony\Component\Filesystem\Path::join(
            $this->cacheDir,
            $this->createCachePath(
                $image->getPath(),
                $coordinates,
                $options
            )
        );

        if ($this->filesystem->exists($cachePath) && !$options->getBypassCache()) {
            return $this->createImage($image, $cachePath);
        }

        return $this->executeResize($image, $coordinates, $cachePath, $options);
    }

    /**
     * Creates the target cache path.
     *
     * @param string            $path
     * @param ResizeCoordinates $coordinates
     * @param ResizeOptions     $options
     *
     * @return string The relative target path
     */
    private function createCachePath($path, ResizeCoordinates $coordinates, ResizeOptions $options)
    {
        $imagineOptions = $options->getImagineOptions();
        ksort($imagineOptions);

        $hashData = array_merge(
            [
                Path::makeRelative($path, $this->cacheDir),
                filemtime($path),
                $coordinates->getHash(),
            ],
            array_keys($imagineOptions),
            array_map(
                static function ($value) {
                    return \is_array($value) ? implode(',', $value) : $value;
                },
                array_values($imagineOptions)
            )
        );

        $hash      = substr(md5(implode('|', $hashData)), 0, 9);
        $pathinfo  = pathinfo($path);
        $extension = $options->getImagineOptions()['format'] ?? strtolower($pathinfo['extension']);

        return Path::join($hash[0], $pathinfo['filename'] . '-' . substr($hash, 1) . '.' . $extension);
    }
}
