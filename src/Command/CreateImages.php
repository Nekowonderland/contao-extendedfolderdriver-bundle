<?php

/**
 * This file is part of ExtendedFolderDriver
 *
 * (c) 2019-2020 Stefan Heimes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package   ExtendedFolderDriver
 * @author    Stefan Heimes <stefan_heimes@hotmail.com>
 * @copyright 2019-2020 Stefan Heimes
 * @license   https://github.com/Nekowonderland/contao-extendedfolderdriver-bundle/blob/master/LICENSE LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Nekowonderland\ExtendedFolderDriver\Command;

use Contao\Config;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\ImageFactory;
use Contao\File;
use Contao\Image\ResizeConfiguration;
use Contao\StringUtil;
use Contao\System;
use FilesystemIterator;
use Nekowonderland\ExtendedFolderDriver\Helper\ImageFilterIterator;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreateImages
 */
class CreateImages extends Command
{
    /**
     * Const values.
     */
    const MODE_CROP         = 'crop';
    const MODE_BOX          = 'box';
    const MODE_PROPORTIONAL = 'proportional';

    /**
     * The name of the command (the part after "bin/console")
     *
     * @var string
     */
    protected static $defaultName = 'contao:nw-generate-images';

    /**
     * List of allowed files. If nothing other is set, the system will use
     * this list.
     *
     * @var array
     */
    protected static $basicAllowedFiles = [
        'png',
        'jpeg',
        'jpg',
        'gif'
    ];

    /**
     * @var array
     */
    static protected $allowedFiles = [];

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Contao image factory.
     *
     * @var ImageFactory
     */
    private $imageFactory;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger       Logger.
     *
     * @param ContaoFramework $framework    Contao.
     *
     * @param ImageFactory    $imageFactory Image factory.
     */
    public function __construct(
        LoggerInterface $logger,
        ContaoFramework $framework,
        ImageFactory $imageFactory
    ) {
        $this->logger       = $logger;
        $this->imageFactory = $imageFactory;
        $this->initContao($framework);

        parent::__construct();
    }

    /**
     * @return array
     */
    public static function getAllowedFiles(): array
    {
        return self::$allowedFiles;
    }

    /**
     * Start contao framework.
     *
     * @param ContaoFramework $framework Contao.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function initContao(ContaoFramework $framework): void
    {
        if (!$framework->isInitialized()) {
            $framework->initialize();
        }

        // Sometimes the language is missing so we will preset is to en.
        if (empty($GLOBALS['TL_LANGUAGE'])) {
            $GLOBALS['TL_LANGUAGE'] = 'en';
        }

        System::loadLanguageFile('default');
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $helpMode = sprintf(
            'Allowed modes are %s, %s, %s',
            self::MODE_CROP,
            self::MODE_BOX,
            self::MODE_PROPORTIONAL
        );

        $this->setDescription('Create for a folder of images all images in he given size.')
             ->setHelp('This command allows you to create a user...')
             ->addOption
             (
                 'path',
                 'p',
                 InputOption::VALUE_OPTIONAL,
                 'The path with should be searched for images or the direct path of the file.'
             )
             ->addOption(
                 'width',
                 'w',
                 InputOption::VALUE_REQUIRED,
                 'The width in pixel.'
             )
             ->addOption(
                 'height',
                 'he',
                 InputOption::VALUE_REQUIRED,
                 'The height in pixel.'
             )
             ->addOption(
                 'zoom',
                 'z',
                 InputOption::VALUE_OPTIONAL,
                 'Zoom level must be between 0 and 100.'
             )
             ->addOption(
                 'mode',
                 'm',
                 InputOption::VALUE_OPTIONAL,
                 $helpMode
             )
             ->addOption(
                 'filetypes',
                 'ft',
                 InputOption::VALUE_OPTIONAL,
                 'Allowed filetype. Use a comma seperated list.'
             );
    }

    /**
     * Get the path without TL_ROOT.
     *
     * @param string $fullPath
     *
     * @return string
     */
    protected function getRelativePath(string $fullPath): string
    {
        return str_replace(TL_ROOT . '/', '', $fullPath);
    }

    /**
     * Check the basic path.
     *
     * @param string $src The path of the file.
     *
     * @return void
     *
     * @throws ResponseException Thrown if something went wrong with the checks. Contains the response.
     */
    protected function checkPath(string $src): void
    {
        if (preg_match('@^\.+@', $src) || preg_match('@\.+/@', $src) || preg_match('@(://)+@', $src)) {
            throw new \RuntimeException('Invalid path');
        }

        if (!preg_match('@^' . preg_quote(Config::get('uploadPath'), '@') . '@i', $src)) {
            throw new \RuntimeException('Invalid path');
        }

        if (!file_exists(TL_ROOT . '/' . $src)) {
            throw new \RuntimeException('File not found');
        }
    }

    /**
     * Check the file.
     *
     * @param \Contao\File $file The file.
     *
     * @return void
     *
     * @throws ResponseException Thrown if something went wrong with the checks. Contains the response.
     */
    protected function checkFile(File $file): void
    {
        if (!empty(self::$allowedFiles) && !\in_array($file->extension, self::$allowedFiles)) {
            throw new \RuntimeException('No allowed file type.');
        }

        if ($file->isSvgImage) {
            throw new \RuntimeException('It is a svg.');
        }

        if (!$file->isImage || $file->height == 0) {
            throw new \RuntimeException('Not an image.');
        }
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return array
     */
    protected function getAllowedFileTypesFromInput(InputInterface $input): array
    {
        $fileTypes = $input->getOption('filetypes');
        if (empty($fileTypes)) {
            return self::$basicAllowedFiles;
        }

        return StringUtil::trimsplit(',', $fileTypes);
    }

    /**
     * Get all parameters from the request and build the resize config for the image class from contao.
     *
     * @param InputInterface $input The current request.
     *
     * @return ResizeConfiguration The configuration.
     */
    protected function getResizeObjectFromInput(InputInterface $input): ResizeConfiguration
    {
        $resizeContainer = new ResizeConfiguration();
        $mode            = $input->getOption('mode');
        $zoom            = $input->getOption('zoom');
        $resizeContainer
            ->setWidth($input->getOption('width'))
            ->setHeight($input->getOption('height'));

        if (!empty($mode)) {
            $resizeContainer->setMode($mode);
        } else {
            $resizeContainer->setMode(ResizeConfiguration::MODE_PROPORTIONAL);
        }

        if (!empty($zoom)) {
            $resizeContainer->setZoomLevel($zoom);
        }

        return $resizeContainer;
    }

    /**
     * Get the path for scanning.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return string
     */
    protected function getPathFromInput(InputInterface $input): string
    {
        $path        = $input->getOption('path');
        $defaultPath = Config::get('uploadPath');
        if (empty($path) && empty($defaultPath)) {
            throw new \RuntimeException('No default path found.');
        }

        if (empty($path)) {
            return (string)$defaultPath;
        }

        $this->checkPath($path);

        return $path;
    }

    /**
     * @param string $path
     *
     * @param array  $allowedFiles
     *
     * @return \SplFileInfo[]|RecursiveIteratorIterator
     */
    protected function getFileListForPath(string $path, array $allowedFiles)
    {
        if (is_file(TL_ROOT . DIRECTORY_SEPARATOR . $path)) {
            return [
                new SplFileInfo(TL_ROOT . DIRECTORY_SEPARATOR . $path)
            ];
        }

        ImageFilterIterator::setFilter($allowedFiles);

        $directoryIt = new RecursiveDirectoryIterator(
            TL_ROOT . DIRECTORY_SEPARATOR . $path,
            FilesystemIterator::UNIX_PATHS | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::SKIP_DOTS
        );

        $filterIt = new ImageFilterIterator(
            $directoryIt
        );

        return new RecursiveIteratorIterator(
            $filterIt,
            RecursiveIteratorIterator::SELF_FIRST
        );
    }

    /**
     * @param ResizeConfiguration $config Config files.
     *
     * @param \SplFileInfo        $file   The file obejct.
     *
     * @return void
     */
    protected function generatePicture(ResizeConfiguration $config, SplFileInfo $file): void
    {
        $fullPath = $file->getPath() . '/' . $file->getFilename();

        $this
            ->imageFactory
            ->create(
                $fullPath,
                $config
            )
            ->getUrl(TL_ROOT);
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!Config::get('thumbnails')) {
            $output->writeln('Thumbnails are disabled on this system.');

            return 0;
        }

        $allowedFiles = $this->getAllowedFileTypesFromInput($input);
        $config       = $this->getResizeObjectFromInput($input);
        $scanPath     = $this->getPathFromInput($input);
        $files        = $this->getFileListForPath($scanPath, $allowedFiles);
        $count        = (is_array($files)) ? count($files) : iterator_count($files);
        $progressBar  = new ProgressBar($output, $count);

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            try {
                $progressBar->advance();
                // We have folders in the list too. This is because we must add them to the list
                // to get the whole list of files. So we have to skip this folders here.
                if ($file->isDir()) {
                    continue;
                }

                $this->generatePicture($config, $file);
            } catch (\Exception $e) {
                $fullPath                                        = $file->getPath() . '/' . $file->getFilename();
                $this->errors[$this->getRelativePath($fullPath)] = $e->getMessage();
            }
        }

        $this->outputError($output);
        $output->writeln("");
        $output->writeln("-- End of Command --");

        return 0;
    }

    /**
     * Output the errors if we have some.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function outputError(OutputInterface $output): void
    {
        if (count($this->errors) == 0) {
            return;
        }

        $output->writeln("Error report:");
        $output->writeln("");
        foreach ($this->errors as $path => $err) {
            $output->writeln
            (
                sprintf
                (
                    "File:\t%s",
                    $path
                )
            );
            $output->writeln("\t\t" . $err);
        }
    }
}