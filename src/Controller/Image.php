<?php

namespace Nekowonderland\ExtendedFolderDriver\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Contao\Image\ResizeConfiguration;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class Image
 *
 * @package Nekowonderland\ExtendedFolderDriver\Controller
 */
class Image
{
    /**
     * @param null   $file
     * @param null   $width
     * @param null   $height
     * @param string $crop
     *
     * @return JsonResponse
     */
    public function generateContaoImage(
        $file = null,
        $width = null,
        $height = null,
        $crop = ResizeConfiguration::MODE_BOX
    ) {
        $newConfig = array($width, $height, $crop);
        if ($file === null) {
            return new JsonResponse([
                'html'      => '<br><p class="preview-image broken-image">Broken image!</p>',
                'state'     => 'error',
                'src'       => '',
                'parameter' => $newConfig
            ]);
        }

        $src = \System::getContainer()
                      ->get('contao.image.image_factory')
                      ->create(
                          TL_ROOT . '/' . rawurldecode($currentEncoded),
                          $newConfig
                      )
                      ->getUrl(TL_ROOT);

        return new JsonResponse([
            'state'     => 'ok',
            'src'       => $src,
            'parameter' => $newConfig
        ]);
    }
}