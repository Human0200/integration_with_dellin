<?php

namespace Dellin\Integration;

use Bitrix\Main\Loader;


Loader::registerAutoLoadClasses(
    'dellin.integration',
    [
        'Dellin\Integration\DellinApi' => 'lib/DellinApi.php',
        'Dellin\Integration\BitrixHelper' => 'lib/BitrixHelper.php',
        'Dellin\Integration\EventHandlers' => 'lib/EventHandlers.php',
    ]
);
?>