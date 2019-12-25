<?php

namespace Contao;

use Contao\Config;
use Contao\Image\ResizeConfiguration;
use Imagine\Exception\RuntimeException;
use Imagine\Gd\Imagine;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class DC_ExtendedFolder
 *
 * @package Contao
 */
class DC_ExtendedFolder extends \DC_Folder
{

    protected function getPathFileRoute($file)
    {
        \System::getContainer()->get('router')
    }


    /**
     * @inheritDoc
     */
    protected function generateTree(
        $path,
        $intMargin,
        $mount = false,
        $blnProtected = true,
        $arrClipboard = null,
        $arrFound = array()
    ) {
        static $session;

        /** @var AttributeBagInterface $objSessionBag */
        $objSessionBag = \System::getContainer()->get('session')->getBag('contao_backend');

        $session = $objSessionBag->all();

        // Get the session data and toggle the nodes
        if (\Input::get('tg')) {
            $session['filetree'][\Input::get('tg')] = (isset($session['filetree'][\Input::get('tg')]) && $session['filetree'][\Input::get('tg')] == 1) ? 0 : 1;
            $objSessionBag->replace($session);
            $this->redirect(preg_replace('/(&(amp;)?|\?)tg=[^& ]*/i', '', \Environment::get('request')));
        }

        $return     = '';
        $files      = array();
        $folders    = array();
        $intSpacing = 20;
        $level      = ($intMargin / $intSpacing + 1);

        // Mount folder
        if ($mount) {
            $folders = array($path);
        } // Scan directory and sort the result
        else {
            foreach (scan($path) as $v) {
                if (strncmp($v, '.', 1) === 0) {
                    continue;
                }

                if (is_file($path . '/' . $v)) {
                    $files[] = $path . '/' . $v;
                } else {
                    if ($v == '__new__') {
                        $this->Files->rrdir(\StringUtil::stripRootDir($path) . '/' . $v);
                    } else {
                        $folders[] = $path . '/' . $v;
                    }
                }
            }

            natcasesort($folders);
            $folders = array_values($folders);

            natcasesort($files);
            $files = array_values($files);
        }

        // Folders
        for ($f = 0, $c = \count($folders); $f < $c; $f++) {
            $md5                       = substr(md5($folders[$f]), 0, 8);
            $content                   = scan($folders[$f]);
            $currentFolder             = \StringUtil::stripRootDir($folders[$f]);
            $session['filetree'][$md5] = is_numeric($session['filetree'][$md5]) ? $session['filetree'][$md5] : 0;
            $currentEncoded            = $this->urlEncode($currentFolder);
            $countFiles                = \count($content);

            // Subtract files that will not be shown
            foreach ($content as $file) {
                if (strncmp($file, '.', 1) === 0) {
                    --$countFiles;
                } elseif (!empty($arrFound) && !\in_array($currentFolder . '/' . $file,
                        $arrFound) && !preg_grep('/^' . preg_quote($currentFolder . '/' . $file, '/') . '\//',
                        $arrFound)) {
                    --$countFiles;
                } elseif (!$this->blnFiles && !$this->blnFilesOnly && !is_dir(TL_ROOT . '/' . $currentFolder . '/' . $file)) {
                    --$countFiles;
                } elseif (!empty($this->arrValidFileTypes) && !is_dir(TL_ROOT . '/' . $currentFolder . '/' . $file)) {
                    $objFile = new \File($currentFolder . '/' . $file);

                    if (!\in_array($objFile->extension, $this->arrValidFileTypes)) {
                        --$countFiles;
                    }
                }
            }

            if (!empty($arrFound) && $countFiles < 1 && !\in_array($currentFolder, $arrFound)) {
                continue;
            }

            $blnIsOpen = (!empty($arrFound) || $session['filetree'][$md5] == 1);

            // Always show selected nodes
            if (!$blnIsOpen && !empty($this->arrPickerValue) && \count(preg_grep('/^' . preg_quote($this->urlEncode($currentFolder),
                        '/') . '\//', $this->arrPickerValue))) {
                $blnIsOpen = true;
            }

            $return .= "\n  " . '<li class="tl_folder click2edit toggle_select hover-div"><div class="tl_left" style="padding-left:' . ($intMargin + (($countFiles < 1) ? 20 : 0)) . 'px">';

            // Add a toggle button if there are childs
            if ($countFiles > 0) {
                $img    = $blnIsOpen ? 'folMinus.svg' : 'folPlus.svg';
                $alt    = $blnIsOpen ? $GLOBALS['TL_LANG']['MSC']['collapseNode'] : $GLOBALS['TL_LANG']['MSC']['expandNode'];
                $return .= '<a href="' . $this->addToUrl('tg=' . $md5) . '" title="' . \StringUtil::specialchars($alt) . '" onclick="Backend.getScrollOffset(); return AjaxRequest.toggleFileManager(this, \'filetree_' . $md5 . '\', \'' . $currentFolder . '\', ' . $level . ')">' . \Image::getHtml($img,
                        '', 'style="margin-right:2px"') . '</a>';
            }

            $protected = $blnProtected;

            // Check whether the folder is public
            if ($protected === true && \in_array('.public', $content)) {
                $protected = false;
            }

            $folderImg = $protected ? 'folderCP.svg' : 'folderC.svg';

            // Add the current folder
            $strFolderNameEncoded = \StringUtil::convertEncoding(
                \StringUtil::specialchars(basename($currentFolder)),
                \Config::get('characterSet')
            );

            $return .= \Image::getHtml($folderImg, '')
                . ' <a href="'
                . $this->addToUrl('fn=' . $currentEncoded)
                . '" title="'
                . \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['selectNode'])
                . '"><strong>'
                . $strFolderNameEncoded
                . '</strong></a></div> <div class="tl_right">';

            // Paste buttons
            if ($arrClipboard !== false && \Input::get('act') != 'select') {
                $imagePasteInto = \Image::getHtml(
                    'pasteinto.svg',
                    $GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][0]
                );

                $return .= (($arrClipboard['mode'] == 'cut' || $arrClipboard['mode'] == 'copy') && preg_match('/^' . preg_quote($arrClipboard['id'],
                            '/') . '/i',
                        $currentFolder)) ? \Image::getHtml('pasteinto_.svg') : '<a href="' . $this->addToUrl('act=' . $arrClipboard['mode'] . '&amp;mode=2&amp;pid=' . $currentEncoded . (!\is_array($arrClipboard['id']) ? '&amp;id=' . $arrClipboard['id'] : '')) . '" title="' . \StringUtil::specialchars($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1]) . '" onclick="Backend.getScrollOffset()">' . $imagePasteInto . '</a> ';
            } // Default buttons
            else {
                // Do not display buttons for mounted folders
                if ($this->User->isAdmin || !\in_array($currentFolder, $this->User->filemounts)) {
                    $return .= (\Input::get('act') == 'select') ? '<input type="checkbox" name="IDS[]" id="ids_' . md5($currentEncoded) . '" class="tl_tree_checkbox" value="' . $currentEncoded . '">' : $this->generateButtons(array(
                        'id'              => $currentEncoded,
                        'fileNameEncoded' => $strFolderNameEncoded
                    ), $this->strTable);
                }

                // Upload button
                if (!$GLOBALS['TL_DCA'][$this->strTable]['config']['closed'] && !$GLOBALS['TL_DCA'][$this->strTable]['config']['notCreatable'] && \Input::get('act') != 'select') {
                    $return .= ' <a href="' . $this->addToUrl('&amp;act=move&amp;mode=2&amp;pid=' . $currentEncoded) . '" title="' . \StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['tl_files']['uploadFF'],
                            $currentEncoded)) . '">' . \Image::getHtml('new.svg',
                            $GLOBALS['TL_LANG'][$this->strTable]['move'][0]) . '</a>';
                }

                if ($this->strPickerFieldType) {
                    $return .= $this->getPickerInputField($currentEncoded, $this->blnFilesOnly ? ' disabled' : '');
                }
            }

            $return .= '</div><div style="clear:both"></div></li>';

            // Call the next node
            if (!empty($content) && $blnIsOpen) {
                $return .= '<li class="parent" id="filetree_' . $md5 . '"><ul class="level_' . $level . '">';
                $return .= $this->generateTree($folders[$f], ($intMargin + $intSpacing), false, $protected,
                    $arrClipboard, $arrFound);
                $return .= '</ul></li>';
            }
        }

        if (!$this->blnFiles && !$this->blnFilesOnly) {
            return $return;
        }

        // Process files
        for ($h = 0, $c = \count($files); $h < $c; $h++) {
            $thumbnail   = '';
            $currentFile = \StringUtil::stripRootDir($files[$h]);

            $objFile = new \File($currentFile);

            if (!empty($this->arrValidFileTypes) && !\in_array($objFile->extension, $this->arrValidFileTypes)) {
                continue;
            }

            // Ignore files not matching the search criteria
            if (!empty($arrFound) && !\in_array($currentFile, $arrFound)) {
                continue;
            }

            $currentEncoded = $this->urlEncode($currentFile);
            $return         .= "\n  " . '<li class="tl_file click2edit toggle_select hover-div"><div class="tl_left" style="padding-left:' . ($intMargin + $intSpacing) . 'px">';
            $thumbnail      .= ' <span class="tl_gray">(' . $this->getReadableSize($objFile->filesize);

            if ($objFile->width && $objFile->height) {
                $thumbnail .= ', ' . $objFile->width . 'x' . $objFile->height . ' px';
            }

            $thumbnail .= ')</span>';

            // Generate the thumbnail
            if (\Config::get('thumbnails') && $objFile->isImage && (!$objFile->isSvgImage || $objFile->viewHeight > 0)) {
                $blnCanResize = true;

                // Check the maximum width and height if the GDlib is used to resize images
                if (!$objFile->isSvgImage && \System::getContainer()->get('contao.image.imagine') instanceof Imagine) {
                    $blnCanResize = $objFile->height <= \Config::get('gdMaxImgHeight') && $objFile->width <= \Config::get('gdMaxImgWidth');
                }

                if ($blnCanResize) {
                    try {
                        // Inline the image if no preview image will be generated (see #636)
                        if ($objFile->height !== null && $objFile->height <= 50 && $objFile->width !== null && $objFile->width <= 400) {
                            $thumbnail .= '<br><img src="' . $objFile->dataUri . '" width="' . $objFile->width . '" height="' . $objFile->height . '" alt="" class="preview-image">';
                        } else {
                            $thumbnail .= '<br>' . \Image::getHtml(
                                    'bundles/extendedfolderdriver/image/loading.gif',
                                    '',
                                    sprintf(
                                        'class="preview-image efd-ajax-image" data-efd-src="%s" data-efd-width="%s" data-efd-height="%s" data-efd-mode="%s"',
                                        base64_encode(rawurldecode($currentEncoded)),
                                        400,
                                        50,
                                        ResizeConfiguration::MODE_BOX
                                    )
                                );
                        }

                        $importantPart = \System::getContainer()->get('contao.image.image_factory')->create(TL_ROOT . '/' . rawurldecode($currentEncoded))->getImportantPart();

                        if ($importantPart->getPosition()->getX() > 0
                            || $importantPart->getPosition()->getY() > 0
                            || $importantPart->getSize()->getWidth() < $objFile->width
                            || $importantPart->getSize()->getHeight() < $objFile->height) {
                            $thumbnail .= ' '
                                . \Image::getHtml(
                                    'bundles/extendedfolderdriver/image/loading.gif',
                                    '',
                                    sprintf(
                                        'class="preview-important efd-ajax-image" data-efd-src="%s" data-efd-width="%s" data-efd-height="%s" data-efd-mode="%s" data-rfg-zoom="%s"',
                                        base64_encode(rawurldecode($currentEncoded)),
                                        320,
                                        40,
                                        ResizeConfiguration::MODE_BOX,
                                        100
                                    )
                                );
                        }
                    } catch (RuntimeException $e) {
                        $thumbnail .= '<br><p class="preview-image broken-image">Broken image!</p>';
                    }
                }
            }

            $strFileNameEncoded = \StringUtil::convertEncoding(\StringUtil::specialchars(basename($currentFile)),
                \Config::get('characterSet'));

            // No popup links for protected files, templates and in the popup file manager
            if ($blnProtected || $this->strTable == 'tl_templates' || \Input::get('popup')) {
                $return .= \Image::getHtml($objFile->icon) . ' ' . $strFileNameEncoded . $thumbnail . '</div> <div class="tl_right">';
            } else {
                $return .= '<a href="' . $currentEncoded . '" title="' . \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['view']) . '" target="_blank">' . \Image::getHtml($objFile->icon,
                        $objFile->mime) . '</a> ' . $strFileNameEncoded . $thumbnail . '</div> <div class="tl_right">';
            }

            // Buttons
            if ($arrClipboard !== false && \Input::get('act') != 'select') {
                $_buttons = '&nbsp;';
            } else {
                $_buttons = (\Input::get('act') == 'select') ? '<input type="checkbox" name="IDS[]" id="ids_' . md5($currentEncoded) . '" class="tl_tree_checkbox" value="' . $currentEncoded . '">' : $this->generateButtons(array(
                    'id'              => $currentEncoded,
                    'fileNameEncoded' => $strFileNameEncoded
                ), $this->strTable);

                if ($this->strPickerFieldType) {
                    $_buttons .= $this->getPickerInputField($currentEncoded);
                }
            }

            $return .= $_buttons . '</div><div style="clear:both"></div></li>';
        }

        return $return;
    }

}