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

namespace Nekowonderland\ExtendedFolderDriver\Controller;

use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\ImageFactory;
use Contao\File;
use Contao\Image\ResizeConfiguration;
use Contao\System;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Image
 *
 * @package Nekowonderland\ExtendedFolderDriver\Controller
 */
class Image
{
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
     * The valid file tpyes as list.
     *
     * @var array
     */
    private $arrValidFileTypes;

    /**
     * The filemounts.
     *
     * @var array
     */
    private $arrFilemounts;

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
        $this->initRightManagement();
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
     * This code is a copy of the DC_Folder driver.
     * I want to check all things like contao, because I didn't want to build a open door to all files.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function initRightManagement(): void
    {
        if ($GLOBALS['TL_DCA']['tl_files']['config']['validFileTypes']) {
            $this->arrValidFileTypes = \StringUtil::trimsplit(
                ',',
                strtolower($GLOBALS['TL_DCA']['tl_files']['config']['validFileTypes'])
            );
        }

        if (\is_array($GLOBALS['TL_DCA']['tl_files']['list']['sorting']['root'])) {
            $this->arrFilemounts = $this
                ->eliminateNestedPaths($GLOBALS['TL_DCA']['tl_files']['list']['sorting']['root']);
        }
    }

    /**
     * Copy from Contao 4.8.x
     *
     * Take an array of file paths and eliminate the nested ones
     *
     * @param array $arrPaths The array of file paths.
     *
     * @return array The file paths array without the nested paths
     */
    protected function eliminateNestedPaths(array $arrPaths): array
    {
        $arrPaths = array_filter($arrPaths);
        if (empty($arrPaths) || !\is_array($arrPaths)) {
            return [];
        }

        $nested = [];
        foreach ($arrPaths as $path) {
            $nested = array_merge($nested, preg_grep('/^' . preg_quote($path, '/') . '\/.+/', $arrPaths));
        }

        return array_values(array_diff($arrPaths, $nested));
    }

    /**
     * Generate a default response.
     *
     * @param string              $state               The state like ok or error.
     *
     * @param string              $msg                 A message for the response.
     *
     * @param string              $src                 The src path of the file to use.
     *
     * @param ResizeConfiguration $resizeConfiguration The configuration for the contao image.
     *
     * @return JsonResponse The response.
     *
     * @throws \Exception If something went wrong to open the file.
     */
    private function getResponse(
        string $state,
        string $msg,
        string $src = '',
        ResizeConfiguration $resizeConfiguration = null
    ): JsonResponse {
        if (!empty($src)) {
            $file = new File(rawurldecode($src));
            $data = base64_encode($file->getContent());
        } else {
            $data = null;
        }

        return new JsonResponse([
            'state'      => $state,
            'message'    => $msg,
            'parameter'  => $resizeConfiguration,
            'src'        => $src,
            'error_html' => '<br><p class="preview-image broken-image">Broken image!</p>',
            'load'       => $data
        ]);
    }

    /**
     * Get all parameters from the request and build the resize config for the image class from contao.
     *
     * @param Request $request The current request.
     *
     * @return ResizeConfiguration The configuration.
     */
    private function getResizeObjectFromRequest(Request $request): ResizeConfiguration
    {
        $resizeContainer = new ResizeConfiguration();
        $mode            = $request->get('mode');
        $zoom            = $request->get('zoom');
        $resizeContainer
            ->setWidth($request->get('width'))
            ->setHeight($request->get('height'));

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
     * Some parts are from the dc driver others are from the sendFileToBrowser function.
     *
     * @param Request $request The current request.
     *
     * @return \Contao\File The file which was requested.
     *
     * @throws ResponseException Thrown if something went wrong with the checks. Contains the response.
     */
    public function getFileFromRequest(Request $request): File
    {
        $src = $request->get('src');
        if (empty($src)) {
            throw new ResponseException($this->getResponse(
                'error',
                'Missing file parameter.'
            ));
        }

        $src = base64_decode($src);
        $this->checkPath($src);

        $file = new File($src);
        $this->checkFile($file);

        return $file;
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
    private function checkPath(string $src): void
    {
        if (preg_match('@^\.+@', $src) || preg_match('@\.+/@', $src) || preg_match('@(://)+@', $src)) {
            throw new ResponseException($this->getResponse(
                'error',
                'Invalid file name'
            ));
        }

        if (!preg_match('@^' . preg_quote(\Config::get('uploadPath'), '@') . '@i', $src)) {
            throw new ResponseException($this->getResponse(
                'error',
                'Invalid path'
            ));
        }

        if (!file_exists(TL_ROOT . '/' . $src)) {
            throw new ResponseException($this->getResponse(
                'error',
                'File not found'
            ));
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
    private function checkFile(File $file): void
    {
        if (!empty($this->arrValidFileTypes) && !\in_array($file->extension, $this->arrValidFileTypes)) {
            throw new ResponseException($this->getResponse(
                'error',
                'No allowed file type.'
            ));
        }

        if ($file->isSvgImage) {
            throw new ResponseException($this->getResponse(
                'error',
                'It is a svg.'
            ));
        }

        if (!$file->isImage || $file->height == 0) {
            throw new ResponseException($this->getResponse(
                'error',
                'Not an image.'
            ));
        }
    }

    /**
     * Generate the image and send a response with all the data and the picture data.
     *
     * @param Request $request The current request.
     *
     * @return Response The response.
     */
    public function generateContaoImage(Request $request): Response
    {
        session_write_close();

        try {
            if (!\Config::get('thumbnails')) {
                return $this->getResponse(
                    'error',
                    'Thumbnails are disabled on this system.'
                );
            }

            $file            = $this->getFileFromRequest($request);
            $resizeContainer = $this->getResizeObjectFromRequest($request);
            $src             = $this
                ->imageFactory
                ->create(
                    TL_ROOT . '/' . $file->path,
                    $resizeContainer
                )
                ->getUrl(TL_ROOT);

            return $this->getResponse(
                'ok',
                '',
                $src,
                $resizeContainer
            );
        } catch (ResponseException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return new JsonResponse([
                'state'      => 'error',
                'message'    => $e->getMessage(),
                'parameter'  => null,
                'src'        => '',
                'error_html' => '<br><p class="preview-image broken-image">Broken image!</p>',
                'load'       => null
            ]);
        }
    }
}
