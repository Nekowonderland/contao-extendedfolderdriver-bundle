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

namespace Contao;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\Image\ResizeConfiguration;
use Imagine\Exception\RuntimeException;
use Imagine\Gd\Imagine;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

/**
 * Class DC_ExtendedFolder
 *
 * @package Contao
 */
class DC_ExtendedFolder extends \DC_Folder
{
    /**
     * @return string
     */
    private function getLoadingHtml()
    {
        return <<<HTML
<div class="nw-ll-group">
    <i class="nw-ll nw-ll-square"></i>
    <i class="nw-ll nw-ll-square"></i>
    <i class="nw-ll nw-ll-square"></i>
</div>
HTML;
    }

    /**
     *
     * @param string   $path   Path to the source file.
     *
     * @param int      $width  Width of the new picture.
     *
     * @param int      $height Height of the new picture.
     *
     * @param string   $mode   Mode of the
     *
     * @param null|int $zoom   Null if not used or a integer between 0 and 100
     *
     * @param bool     $prviewWidget
     *
     * @param int      $originWidth
     *
     * @param int      $originHeight
     *
     * @return string The image with all data.
     */
    private function getAjaxImageTag(
        $path,
        $width = 400,
        $height = 50,
        $mode = ResizeConfiguration::MODE_BOX,
        $zoom = null,
        $prviewWidget = false,
        $originWidth = 0,
        $originHeight = 0
    ) {
        $attributes = [
            'class="%s"'           => 'preview-image efd-ajax-image',
            'data-efd-src="%s"'    => base64_encode(rawurldecode($path)),
            'data-efd-width="%s"'  => $width,
            'data-efd-height="%s"' => $height,
            'data-efd-mode="%s"'   => $mode,
            'data-efg-broken="%s"' => ($GLOBALS['TL_DCA']['tl_settings']['fields']['nwHiddenDmgImage']) ? 1 : 0
        ];

        if ($zoom != null) {
            $attributes['data-efd-zoom="%s"'] = $zoom;
        }

        if ($prviewWidget != false) {
            $attributes['data-efd-preview="%s"'] = 1;
        }

        if ($originHeight != 0) {
            $attributes['data-efd-origin-width="%s"'] = $originWidth;
        }

        if ($originHeight != 0) {
            $attributes['data-efd-origin-height="%s"'] = $originHeight;
        }

        return \Image::getHtml(
            'bundles/extendedfolderdriver/image/loading.gif',
            '',
            vsprintf(
                implode(' ', array_keys($attributes)),
                array_values($attributes)
            )
        );
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
                            $thumbnail .= '<br>' . $this->getAjaxImageTag(
                                    $currentEncoded,
                                    400,
                                    50,
                                    ResizeConfiguration::MODE_BOX
                                );
                        }

                        $importantPart = \System::getContainer()
                                                ->get('contao.image.image_factory')
                                                ->create(TL_ROOT . '/' . rawurldecode($currentEncoded))
                                                ->getImportantPart();

                        if ($importantPart->getPosition()->getX() > 0
                            || $importantPart->getPosition()->getY() > 0
                            || $importantPart->getSize()->getWidth() < $objFile->width
                            || $importantPart->getSize()->getHeight() < $objFile->height) {
                            $thumbnail .= ' ' . $this->getAjaxImageTag(
                                    $currentEncoded,
                                    320,
                                    40,
                                    ResizeConfiguration::MODE_BOX,
                                    100
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

    protected function row($strPalette = null)
    {
        $arrData = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField];

        // Check if the field is excluded
        if ($arrData['exclude']) {
            throw new AccessDeniedException('Field "' . $this->strTable . '.' . $this->strField . '" is excluded from being edited.');
        }

        $xlabel = '';

        // Toggle line wrap (textarea)
        if ($arrData['inputType'] == 'textarea' && !isset($arrData['eval']['rte'])) {
            $xlabel .= ' ' . \Image::getHtml('wrap.svg', $GLOBALS['TL_LANG']['MSC']['wordWrap'],
                    'title="' . \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['wordWrap']) . '" class="toggleWrap" onclick="Backend.toggleWrap(\'ctrl_' . $this->strInputName . '\')"');
        }

        // Add the help wizard
        if ($arrData['eval']['helpwizard']) {
            $xlabel .= ' <a href="contao/help.php?table=' . $this->strTable . '&amp;field=' . $this->strField . '" title="' . \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['helpWizard']) . '" onclick="Backend.openModalIframe({\'title\':\'' . \StringUtil::specialchars(str_replace("'",
                    "\\'",
                    $arrData['label'][0])) . '\',\'url\':this.href});return false">' . \Image::getHtml('about.svg',
                    $GLOBALS['TL_LANG']['MSC']['helpWizard']) . '</a>';
        }

        // Add a custom xlabel
        if (\is_array($arrData['xlabel'])) {
            foreach ($arrData['xlabel'] as $callback) {
                if (\is_array($callback)) {
                    $this->import($callback[0]);
                    $xlabel .= $this->{$callback[0]}->{$callback[1]}($this);
                } elseif (\is_callable($callback)) {
                    $xlabel .= $callback($this);
                }
            }
        }

        // Input field callback
        if (\is_array($arrData['input_field_callback'])) {
            $this->import($arrData['input_field_callback'][0]);

            return $this->{$arrData['input_field_callback'][0]}->{$arrData['input_field_callback'][1]}($this, $xlabel);
        }

        if (\is_callable($arrData['input_field_callback'])) {
            return $arrData['input_field_callback']($this, $xlabel);
        }

        /** @var Widget $strClass */
        $strClass = $GLOBALS['BE_FFL'][$arrData['inputType']];

        // Return if the widget class does not exists
        if (!class_exists($strClass)) {
            return '';
        }

        $arrData['eval']['required'] = false;

        // Use strlen() here (see #3277)
        if ($arrData['eval']['mandatory']) {
            if (\is_array($this->varValue)) {
                if (empty($this->varValue)) {
                    $arrData['eval']['required'] = true;
                }
            } else {
                if (!\strlen($this->varValue)) {
                    $arrData['eval']['required'] = true;
                }
            }
        }

        // Convert insert tags in src attributes (see #5965)
        if (isset($arrData['eval']['rte']) && strncmp($arrData['eval']['rte'], 'tiny', 4) === 0) {
            $this->varValue = \StringUtil::insertTagToSrc($this->varValue);
        }

        // Use raw request if set globally but allow opting out setting useRawRequestData to false explicitly
        $useRawGlobally = isset($GLOBALS['TL_DCA'][$this->strTable]['config']['useRawRequestData']) && $GLOBALS['TL_DCA'][$this->strTable]['config']['useRawRequestData'] === true;
        $notRawForField = isset($arrData['eval']['useRawRequestData']) && $arrData['eval']['useRawRequestData'] === false;

        if ($useRawGlobally && !$notRawForField) {
            $arrData['eval']['useRawRequestData'] = true;
        }

        /** @var Widget $objWidget */
        $objWidget = new $strClass($strClass::getAttributesFromDca($arrData, $this->strInputName, $this->varValue,
            $this->strField, $this->strTable, $this));

        $objWidget->xlabel        = $xlabel;
        $objWidget->currentRecord = $this->intId;

        // Validate the field
        if (\Input::post('FORM_SUBMIT') == $this->strTable) {
            $suffix = ($this instanceof DC_Folder ? md5($this->intId) : $this->intId);
            $key    = (\Input::get('act') == 'editAll') ? 'FORM_FIELDS_' . $suffix : 'FORM_FIELDS';

            // Calculate the current palette
            $postPaletteFields = implode(',', \Input::post($key));
            $postPaletteFields = array_unique(\StringUtil::trimsplit('[,;]', $postPaletteFields));

            // Compile the palette if there is none
            if ($strPalette === null) {
                $newPaletteFields = \StringUtil::trimsplit('[,;]', $this->getPalette());
            } else {
                // Use the given palette ($strPalette is an array in editAll mode)
                $newPaletteFields = \is_array($strPalette) ? $strPalette : \StringUtil::trimsplit('[,;]', $strPalette);

                // Re-check the palette if the current field is a selector field
                if (isset($GLOBALS['TL_DCA'][$this->strTable]['palettes']['__selector__']) && \in_array($this->strField,
                        $GLOBALS['TL_DCA'][$this->strTable]['palettes']['__selector__'])) {
                    // If the field value has changed, recompile the palette
                    if ($this->varValue != \Input::post($this->strInputName)) {
                        $newPaletteFields = \StringUtil::trimsplit('[,;]', $this->getPalette());
                    }
                }
            }

            // Adjust the names in editAll mode
            if (\Input::get('act') == 'editAll') {
                foreach ($newPaletteFields as $k => $v) {
                    $newPaletteFields[$k] = $v . '_' . $suffix;
                }

                if ($this->User->isAdmin) {
                    $newPaletteFields['pid']     = 'pid_' . $suffix;
                    $newPaletteFields['sorting'] = 'sorting_' . $suffix;
                }
            }

            $paletteFields = array_intersect($postPaletteFields, $newPaletteFields);

            // Deprecated since Contao 4.2, to be removed in Contao 5.0
            if (!isset($_POST[$this->strInputName]) && \in_array($this->strInputName, $paletteFields)) {
                @trigger_error('Using FORM_FIELDS has been deprecated and will no longer work in Contao 5.0. Make sure to always submit at least an empty string in your widget.',
                    E_USER_DEPRECATED);
            }

            // Validate and save the field
            if (\in_array($this->strInputName, $paletteFields) || \Input::get('act') == 'overrideAll') {
                $objWidget->validate();

                if ($objWidget->hasErrors()) {
                    // Skip mandatory fields on auto-submit (see #4077)
                    if (\Input::post('SUBMIT_TYPE') != 'auto' || !$objWidget->mandatory || $objWidget->value != '') {
                        $this->noReload = true;
                    }
                } elseif ($objWidget->submitInput()) {
                    $varValue = $objWidget->value;

                    // Sort array by key (fix for JavaScript wizards)
                    if (\is_array($varValue)) {
                        ksort($varValue);
                        $varValue = serialize($varValue);
                    }

                    // Convert file paths in src attributes (see #5965)
                    if ($varValue && isset($arrData['eval']['rte']) && strncmp($arrData['eval']['rte'], 'tiny',
                            4) === 0) {
                        $varValue = \StringUtil::srcToInsertTag($varValue);
                    }

                    // Save the current value
                    try {
                        $this->save($varValue);
                    } catch (ResponseException $e) {
                        throw $e;
                    } catch (\Exception $e) {
                        $this->noReload = true;
                        $objWidget->addError($e->getMessage());
                    }
                }
            }
        }

        $wizard       = '';
        $strHelpClass = '';

        // Date picker
        if ($arrData['eval']['datepicker']) {
            $rgxp   = $arrData['eval']['rgxp'];
            $format = \Date::formatToJs(\Config::get($rgxp . 'Format'));

            switch ($rgxp) {
                case 'datim':
                    $time = ",\n        timePicker: true";
                    break;

                case 'time':
                    $time = ",\n        pickOnly: \"time\"";
                    break;

                default:
                    $time = '';
                    break;
            }

            $strOnSelect = '';

            // Trigger the auto-submit function (see #8603)
            if ($arrData['eval']['submitOnChange']) {
                $strOnSelect = ",\n        onSelect: function() { Backend.autoSubmit(\"" . $this->strTable . "\"); }";
            }

            $wizard .= ' ' . \Image::getHtml('assets/datepicker/images/icon.svg', '',
                    'title="' . \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['datepicker']) . '" id="toggle_' . $objWidget->id . '" style="cursor:pointer"') . '
  <script>
    window.addEvent("domready", function() {
      new Picker.Date($("ctrl_' . $objWidget->id . '"), {
        draggable: false,
        toggle: $("toggle_' . $objWidget->id . '"),
        format: "' . $format . '",
        positionOffset: {x:-211,y:-209}' . $time . ',
        pickerClass: "datepicker_bootstrap",
        useFadeInOut: !Browser.ie' . $strOnSelect . ',
        startDay: ' . $GLOBALS['TL_LANG']['MSC']['weekOffset'] . ',
        titleFormat: "' . $GLOBALS['TL_LANG']['MSC']['titleFormat'] . '"
      });
    });
  </script>';
        }

        // Color picker
        if ($arrData['eval']['colorpicker']) {
            // Support single fields as well (see #5240)
            $strKey = $arrData['eval']['multiple'] ? $this->strField . '_0' : $this->strField;

            $wizard .= ' ' . \Image::getHtml('pickcolor.svg', $GLOBALS['TL_LANG']['MSC']['colorpicker'],
                    'title="' . \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['colorpicker']) . '" id="moo_' . $this->strField . '" style="cursor:pointer"') . '
  <script>
    window.addEvent("domready", function() {
      var cl = $("ctrl_' . $strKey . '").value.hexToRgb(true) || [255, 0, 0];
      new MooRainbow("moo_' . $this->strField . '", {
        id: "ctrl_' . $strKey . '",
        startColor: cl,
        imgPath: "assets/colorpicker/images/",
        onComplete: function(color) {
          $("ctrl_' . $strKey . '").value = color.hex.replace("#", "");
        }
      });
    });
  </script>';
        }

        // DCA picker
        if (isset($arrData['eval']['dcaPicker']) && (\is_array($arrData['eval']['dcaPicker']) || $arrData['eval']['dcaPicker'] === true)) {
            $wizard .= \Backend::getDcaPickerWizard($arrData['eval']['dcaPicker'], $this->strTable, $this->strField,
                $this->strInputName);
        }

        // Add a custom wizard
        if (\is_array($arrData['wizard'])) {
            foreach ($arrData['wizard'] as $callback) {
                if (\is_array($callback)) {
                    $this->import($callback[0]);
                    $wizard .= $this->{$callback[0]}->{$callback[1]}($this);
                } elseif (\is_callable($callback)) {
                    $wizard .= $callback($this);
                }
            }
        }

        $arrClasses = \StringUtil::trimsplit(' ', (string)$arrData['eval']['tl_class']);

        if ($wizard != '') {
            $objWidget->wizard = $wizard;

            if (!\in_array('wizard', $arrClasses)) {
                $arrClasses[] = 'wizard';
            }
        } elseif (\in_array('wizard', $arrClasses)) {
            unset($arrClasses[array_search('wizard', $arrClasses)]);
        }

        // Set correct form enctype
        if ($objWidget instanceof \uploadable) {
            $this->blnUploadable = true;
        }

        if ($arrData['inputType'] != 'password') {
            $arrClasses[] = 'widget';
        }

        // Mark floated single checkboxes
        if ($arrData['inputType'] == 'checkbox' && !$arrData['eval']['multiple'] && \in_array('w50', $arrClasses)) {
            $arrClasses[] = 'cbx';
        } elseif ($arrData['inputType'] == 'text' && $arrData['eval']['multiple'] && \in_array('wizard', $arrClasses)) {
            $arrClasses[] = 'inline';
        }

        if (!empty($arrClasses)) {
            $arrData['eval']['tl_class'] = implode(' ', array_unique($arrClasses));
        }

        $updateMode = '';

        // Replace the textarea with an RTE instance
        if (!empty($arrData['eval']['rte'])) {
            list ($file, $type) = explode('|', $arrData['eval']['rte'], 2);

            $fileBrowserTypes = array();
            $pickerBuilder    = \System::getContainer()->get('contao.picker.builder');

            foreach (array('file' => 'image', 'link' => 'file') as $context => $fileBrowserType) {
                if ($pickerBuilder->supportsContext($context)) {
                    $fileBrowserTypes[] = $fileBrowserType;
                }
            }

            /** @var BackendTemplate|object $objTemplate */
            $objTemplate                   = new \BackendTemplate('be_' . $file);
            $objTemplate->selector         = 'ctrl_' . $this->strInputName;
            $objTemplate->type             = $type;
            $objTemplate->fileBrowserTypes = $fileBrowserTypes;
            $objTemplate->source           = $this->strTable . '.' . $this->intId;

            // Deprecated since Contao 4.0, to be removed in Contao 5.0
            $objTemplate->language = \Backend::getTinyMceLanguage();

            $updateMode = $objTemplate->parse();

            unset($file, $type, $pickerBuilder, $fileBrowserTypes, $fileBrowserType);
        } // Handle multi-select fields in "override all" mode
        elseif (\Input::get('act') == 'overrideAll' && ($arrData['inputType'] == 'checkbox' || $arrData['inputType'] == 'checkboxWizard') && $arrData['eval']['multiple']) {
            $updateMode = '
</div>
<div class="widget">
  <fieldset class="tl_radio_container">
  <legend>' . $GLOBALS['TL_LANG']['MSC']['updateMode'] . '</legend>
    <input type="radio" name="' . $this->strInputName . '_update" id="opt_' . $this->strInputName . '_update_1" class="tl_radio" value="add" onfocus="Backend.getScrollOffset()"> <label for="opt_' . $this->strInputName . '_update_1">' . $GLOBALS['TL_LANG']['MSC']['updateAdd'] . '</label><br>
    <input type="radio" name="' . $this->strInputName . '_update" id="opt_' . $this->strInputName . '_update_2" class="tl_radio" value="remove" onfocus="Backend.getScrollOffset()"> <label for="opt_' . $this->strInputName . '_update_2">' . $GLOBALS['TL_LANG']['MSC']['updateRemove'] . '</label><br>
    <input type="radio" name="' . $this->strInputName . '_update" id="opt_' . $this->strInputName . '_update_0" class="tl_radio" value="replace" checked="checked" onfocus="Backend.getScrollOffset()"> <label for="opt_' . $this->strInputName . '_update_0">' . $GLOBALS['TL_LANG']['MSC']['updateReplace'] . '</label>
  </fieldset>';
        }

        $strPreview = '';

        // Show a preview image (see #4948)
        if ($this->strTable == 'tl_files' && $this->strField == 'name' && $this->objActiveRecord !== null && $this->objActiveRecord->type == 'file') {
            $objFile = new \File($this->objActiveRecord->path);

            if ($objFile->isImage) {
                $blnCanResize = true;

                // Check the maximum width and height if the GDlib is used to resize images
                if (!$objFile->isSvgImage && \System::getContainer()->get('contao.image.imagine') instanceof Imagine) {
                    $blnCanResize = $objFile->height <= \Config::get('gdMaxImgHeight') && $objFile->width <= \Config::get('gdMaxImgWidth');
                }

                $image = \Image::getPath('placeholder.svg');

                if ($blnCanResize) {
                    if ($objFile->width > 699 || $objFile->height > 524 || !$objFile->width || !$objFile->height) {
                        $strPreview = $this->getAjaxImageTag(
                            $objFile->path,
                            699,
                            524,
                            ResizeConfiguration::MODE_BOX,
                            null,
                            true,
                            $objFile->viewWidth,
                            $objFile->viewHeight
                        );
                        if (\Config::get('showHelp')) {
                            $strPreview .= '<p class="tl_help tl_tip">' . $GLOBALS['TL_LANG'][$this->strTable]['edit_preview_help'] . '</p>';
                        }
                        $strPreview = '<div class="widget">' . $strPreview . '</div>';
                    } else {
                        $image = $objFile->path;

                        $objImage = new \File($image);
                        $ctrl     = 'ctrl_preview_' . substr(md5($image), 0, 8);

                        $strPreview = '
<div id="' . $ctrl . '" class="tl_edit_preview" data-original-width="' . $objFile->viewWidth . '" data-original-height="' . $objFile->viewHeight . '">
  <img src="' . $objImage->dataUri . '" width="' . $objImage->width . '" height="' . $objImage->height . '" alt="">
</div>';

                        // Add the script to mark the important part
                        if (basename($image) !== 'placeholder.svg') {
                            $strPreview .= '<script>Backend.editPreviewWizard($(\'' . $ctrl . '\'));</script>';

                            if (\Config::get('showHelp')) {
                                $strPreview .= '<p class="tl_help tl_tip">' . $GLOBALS['TL_LANG'][$this->strTable]['edit_preview_help'] . '</p>';
                            }

                            $strPreview = '<div class="widget">' . $strPreview . '</div>';
                        }
                    }
                }
            }
        }

        return $strPreview . '
<div' . ($arrData['eval']['tl_class'] ? ' class="' . trim($arrData['eval']['tl_class']) . '"' : '') . '>' . $objWidget->parse() . $updateMode . (!$objWidget->hasErrors() ? $this->help($strHelpClass) : '') . '
</div>';
    }


}