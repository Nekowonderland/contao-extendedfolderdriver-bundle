<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Extend default palette
PaletteManipulator::create()
                  ->addField(
                      'nwHiddenDmgImage',
                      'gdMaxImgHeight',
                      PaletteManipulator::POSITION_AFTER,
                      'files_legend',
                      PaletteManipulator::POSITION_PREPEND
                  )
                  ->addField(
                      'nwAjaxMaxImgHeight',
                      'gdMaxImgHeight',
                      PaletteManipulator::POSITION_AFTER,
                      'files_legend',
                      PaletteManipulator::POSITION_PREPEND
                  )
                  ->addField(
                      'nwAjaxMaxImgWidth',
                      'gdMaxImgHeight',
                      PaletteManipulator::POSITION_AFTER,
                      'files_legend',
                      PaletteManipulator::POSITION_PREPEND
                  )
                  ->applyToPalette('default', 'tl_settings');

// Extend fields
$GLOBALS['TL_DCA']['tl_settings']['fields']['nwAjaxMaxImgHeight'] =
    [
        'label'     => &$GLOBALS['TL_LANG']['tl_settings']['nwAjaxMaxImgHeight'],
        'inputType' => 'text',
        'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'nospace' => true, 'tl_class' => 'w50']
    ];

$GLOBALS['TL_DCA']['tl_settings']['fields']['nwAjaxMaxImgWidth'] =
    [
        'label'     => &$GLOBALS['TL_LANG']['tl_settings']['nwAjaxMaxImgWidth'],
        'inputType' => 'text',
        'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'nospace' => true, 'tl_class' => 'w50']
    ];

$GLOBALS['TL_DCA']['tl_settings']['fields']['nwHiddenDmgImage'] =
    [
        'label'     => &$GLOBALS['TL_LANG']['tl_settings']['nwHiddenDmgImage'],
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50']
    ];