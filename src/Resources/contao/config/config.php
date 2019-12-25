<?php

$GLOBALS['TL_HOOKS']['loadDataContainer'][] = array(
    Nekowonderland\ExtendedFolderDriver\Listeners\LoadDataContainerListener::class,
    'updateFolderSettings'
);
