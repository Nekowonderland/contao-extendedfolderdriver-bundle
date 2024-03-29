<?php


namespace Nekowonderland\ExtendedFolderDriver\Resizer;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\CoreBundle\Image\ImageFactoryInterface;
use Contao\FilesModel;
use Contao\Image\DeferredResizerInterface;
use Contao\Image\Image;
use Contao\Image\ImageInterface;
use Contao\Image\ImportantPart;
use Contao\Image\ResizeConfiguration;
use Contao\Image\ResizeConfigurationInterface;
use Contao\Image\ResizeOptions;
use Contao\Image\ResizerInterface;
use Contao\ImageSizeModel;
use Contao\StringUtil;
use Imagine\Image\Box;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Creates Image objects.
 *
 * @author Martin Auswöger <martin@auswoeger.com>
 */
class ImageFactory implements ImageFactoryInterface
{
    /**
     * @var ResizerInterface
     */
    private $resizer;

    /**
     * @var ImagineInterface
     */
    private $imagine;

    /**
     * @var ImagineInterface
     */
    private $imagineSvg;

    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var bool
     */
    private $bypassCache;

    /**
     * @var array
     */
    private $imagineOptions;

    /**
     * @var array
     */
    private $validExtensions;

    /**
     * Constructor.
     *
     * @param ResizerInterface         $resizer
     * @param ImagineInterface         $imagine
     * @param ImagineInterface         $imagineSvg
     * @param Filesystem               $filesystem
     * @param ContaoFrameworkInterface $framework
     * @param bool                     $bypassCache
     * @param array                    $imagineOptions
     * @param array                    $validExtensions
     */
    public function __construct(
        ResizerInterface $resizer,
        ImagineInterface $imagine,
        ImagineInterface $imagineSvg,
        Filesystem $filesystem,
        ContaoFrameworkInterface $framework,
        $bypassCache,
        array $imagineOptions,
        array $validExtensions
    ) {
        $this->resizer         = $resizer;
        $this->imagine         = $imagine;
        $this->imagineSvg      = $imagineSvg;
        $this->filesystem      = $filesystem;
        $this->framework       = $framework;
        $this->bypassCache     = (bool)$bypassCache;
        $this->imagineOptions  = $imagineOptions;
        $this->validExtensions = $validExtensions;
    }

    /**
     * {@inheritdoc}
     */
    public function create($path, $size = null, $options = null): ImageInterface
    {
        if (null !== $options && !\is_string($options) && !$options instanceof ResizeOptions) {
            throw new \InvalidArgumentException('Options must be of type null, string or ' . ResizeOptions::class);
        }

        if ($path instanceof ImageInterface) {
            $image = $path;
        } else {
            $path          = (string)$path;
            $fileExtension = Path::getExtension($path, true);

            if (\in_array($fileExtension, ['svg', 'svgz'], true)) {
                $imagine = $this->imagineSvg;
            } else {
                $imagine = $this->imagine;
            }

            if (!\in_array($fileExtension, $this->validExtensions, true)) {
                throw new \InvalidArgumentException(sprintf('Image type "%s" was not allowed to be processed',
                    $fileExtension));
            }

            if (!Path::isAbsolute($path)) {
                throw new \InvalidArgumentException(sprintf('Image path "%s" must be absolute', $path));
            }

            if (
                $this->resizer instanceof DeferredResizerInterface
                && !$this->filesystem->exists($path)
                && $deferredImage = $this->resizer->getDeferredImage($path, $imagine)
            ) {
                $image = $deferredImage;
            } else {
                $image = new Image($path, $imagine, $this->filesystem);
            }
        }

        $targetPath = $options instanceof ResizeOptions ? $options->getTargetPath() : $options;

        // Support arrays in a serialized form
        $size = StringUtil::deserialize($size);

        if ($size instanceof ResizeConfiguration) {
            $resizeConfig  = $size;
            $importantPart = null;
        } else {
            [$resizeConfig, $importantPart, $options] = $this->createConfig($size, $image);
        }

        if (!\is_object($path) || !$path instanceof ImageInterface) {
            if (null === $importantPart) {
                $importantPart = $this->createImportantPart($image);
            }

            $image->setImportantPart($importantPart);
        }

        if (null === $options && null === $targetPath && null === $size) {
            return $image;
        }

        if (!$options instanceof ResizeOptions) {
            $options = new ResizeOptions();

            if (!$size instanceof ResizeConfiguration && $resizeConfig->isEmpty()) {
                $options->setSkipIfDimensionsMatch(true);
            }
        }

        if (null !== $targetPath) {
            $options->setTargetPath($targetPath);
        }

        if (!$options->getImagineOptions()) {
            $options->setImagineOptions($this->imagineOptions);
        }

        $options->setBypassCache($options->getBypassCache() || $this->bypassCache);

        return $this->resizer->resize($image, $resizeConfig, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getImportantPartFromLegacyMode(ImageInterface $image, $mode)
    {
        if (1 !== substr_count($mode, '_')) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a legacy resize mode', $mode));
        }

        $importantPart = [
            0,
            0,
            $image->getDimensions()->getSize()->getWidth(),
            $image->getDimensions()->getSize()->getHeight(),
        ];

        list($modeX, $modeY) = explode('_', $mode);

        if ('left' === $modeX) {
            $importantPart[2] = 1;
        } elseif ('right' === $modeX) {
            $importantPart[0] = $importantPart[2] - 1;
            $importantPart[2] = 1;
        }

        if ('top' === $modeY) {
            $importantPart[3] = 1;
        } elseif ('bottom' === $modeY) {
            $importantPart[1] = $importantPart[3] - 1;
            $importantPart[3] = 1;
        }

        return new ImportantPart(
            new Point($importantPart[0], $importantPart[1]),
            new Box($importantPart[2], $importantPart[3])
        );
    }

    /**
     * Creates a resize configuration object.
     *
     * @param int|array|null $size An image size or an array with width, height and resize mode
     * @param ImageInterface $image
     *
     * @return array
     */
    private function createConfig($size, ImageInterface $image)
    {
        if (!\is_array($size)) {
            $size = [0, 0, $size];
        }

        $config  = new ResizeConfiguration();
        $options = new ResizeOptions();
        if (isset($size[2])) {
            // Database record
            if (is_numeric($size[2])) {
                $imageModel = $this->framework->getAdapter(ImageSizeModel::class);

                if (null !== ($imageSize = $imageModel->findByPk($size[2]))) {
                    $this->enhanceResizeConfig($config, $imageSize->row());
                    $options->setSkipIfDimensionsMatch((bool)$imageSize->skipIfDimensionsMatch);
                }

                return [$config, null, $options];
            }

            // Predefined sizes
            if (isset($this->predefinedSizes[$size[2]])) {
                $this->enhanceResizeConfig($config, $this->predefinedSizes[$size[2]]);
                $options->setSkipIfDimensionsMatch($this->predefinedSizes[$size[2]]['skipIfDimensionsMatch'] ?? false);

                return [$config, null, $options];
            }
        }

        if (!empty($size[0])) {
            $config->setWidth((int)$size[0]);
        }

        if (!empty($size[1])) {
            $config->setHeight((int)$size[1]);
        }

        if (!isset($size[2])
            || (\is_string($size[2]) && 1 !== substr_count($size[2], '_'))
        ) {
            if (!empty($size[2])) {
                $config->setMode($size[2]);
            }

            return [$config, null, null];
        }

        $config->setMode(ResizeConfiguration::MODE_CROP);

        return [$config, $this->getImportantPartFromLegacyMode($image, $size[2]), null];
    }

    /**
     * Fetches the important part from the database.
     *
     * @param ImageInterface $image
     *
     * @return ImportantPart|null
     */
    private function createImportantPart(ImageInterface $image)
    {
        /** @var FilesModel $filesModel */
        $filesModel = $this->framework->getAdapter(FilesModel::class);
        $file       = $filesModel->findByPath($image->getPath());

        if (null === $file || !$file->importantPartWidth || !$file->importantPartHeight) {
            return null;
        }

        $imageSize = $image->getDimensions()->getSize();

        if (
            $file->importantPartX + $file->importantPartWidth > $imageSize->getWidth()
            || $file->importantPartY + $file->importantPartHeight > $imageSize->getHeight()
        ) {
            return null;
        }

        return new ImportantPart(
            new Point((int)$file->importantPartX, (int)$file->importantPartY),
            new Box((int)$file->importantPartWidth, (int)$file->importantPartHeight)
        );
    }
}
