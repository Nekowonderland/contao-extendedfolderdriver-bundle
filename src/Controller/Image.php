<?php declare(strict_types=1);

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ImageFactory
     */
    private $imageFactory;

    /**
     * @var array
     */
    private $arrValidFileTypes;

    /**
     * @var array
     */
    private $arrFilemounts;

    /**
     * Client constructor.
     *
     * @param LoggerInterface $logger Logger.
     *
     * @param ContaoFramework $framework
     *
     * @param ImageFactory    $imageFactory
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
     * @param ContaoFramework $framework
     *
     * @return void
     */
    private function initContao(
        ContaoFramework $framework
    ): void {
        if (!$framework->isInitialized()) {
            $framework->initialize();
        }

        // Preset the language.
        if (empty($GLOBALS['TL_LANGUAGE'])) {
            $GLOBALS['TL_LANGUAGE'] = 'en';
        }

        System::loadLanguageFile('default');
    }

    /**
     * This code is a copy of the DC_Folder driver.
     * I want to check all things like contao, because I didn't want to build a open door to all files.
     */
    public function initRightManagement(): void
    {
        // Check for valid file types
        if ($GLOBALS['TL_DCA']['tl_files']['config']['validFileTypes']) {
            $this->arrValidFileTypes = \StringUtil::trimsplit(
                ',',
                strtolower($GLOBALS['TL_DCA']['tl_files']['config']['validFileTypes'])
            );
        }

        // Get all filemounts (root folders)
        if (\is_array($GLOBALS['TL_DCA']['tl_files']['list']['sorting']['root'])) {
            $this->arrFilemounts = $this->eliminateNestedPaths($GLOBALS['TL_DCA']['tl_files']['list']['sorting']['root']);
        }
    }

    /**
     * Copy from Contao 4.8.x
     *
     * Take an array of file paths and eliminate the nested ones
     *
     * @param array $arrPaths The array of file paths
     *
     * @return array The file paths array without the nested paths
     */
    protected function eliminateNestedPaths(array $arrPaths): array
    {
        $arrPaths = array_filter($arrPaths);

        if (empty($arrPaths) || !\is_array($arrPaths)) {
            return array();
        }

        $nested = array();

        foreach ($arrPaths as $path) {
            $nested = array_merge($nested, preg_grep('/^' . preg_quote($path, '/') . '\/.+/', $arrPaths));
        }

        return array_values(array_diff($arrPaths, $nested));
    }

    /**
     * @param string              $state
     * @param string              $msg
     * @param string              $src
     * @param ResizeConfiguration $resizeConfiguration
     *
     * @return JsonResponse
     * @throws \Exception
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
     * @param Request $request
     *
     * @return ResizeConfiguration
     */
    private function getResizeObjectFromRequest(
        Request $request
    ): ResizeConfiguration {
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
     * @param Request $request
     *
     * @return \Contao\File
     * @throws \Exception
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

        // Make sure there are no attempts to hack the file system
        if (preg_match('@^\.+@', $src) || preg_match('@\.+/@', $src) || preg_match('@(://)+@', $src)) {
            throw new ResponseException($this->getResponse(
                'error',
                'Invalid file name'
            ));
        }

        // Limit downloads to the files directory
        if (!preg_match('@^' . preg_quote(\Config::get('uploadPath'), '@') . '@i', $src)) {
            throw new ResponseException($this->getResponse(
                'error',
                'Invalid path'
            ));
        }

        // Check whether the file exists
        if (!file_exists(TL_ROOT . '/' . $src)) {
            throw new ResponseException($this->getResponse(
                'error',
                'File not found'
            ));
        }

        $file = new File($src);

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

        // ToDo: Check file mounts from the user.

        return $file;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function generateContaoImage(
        Request $request
    ): Response {
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