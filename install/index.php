<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;

Loc::loadMessages(__FILE__);

class dellin_integration extends CModule
{
    public $MODULE_ID = 'dellin.integration';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . '/version.php');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = 'Интеграция с Деловыми Линиями';
        $this->MODULE_DESCRIPTION = 'Модуль для интеграции Битрикс24 с API Деловых Линий';
        $this->PARTNER_NAME = 'Lead Space';
        $this->PARTNER_URI = 'https://lead-space.ru';
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (CheckVersion(ModuleManager::getVersion('main'), '14.00.00')) {
            ModuleManager::registerModule($this->MODULE_ID);
            $this->InstallEvents();
            
            $APPLICATION->IncludeAdminFile(
                'Установка модуля ' . $this->MODULE_NAME,
                __DIR__ . '/step.php'
            );
        } else {
            $APPLICATION->ThrowException('Требуется версия главного модуля 14.00.00 и выше');
        }

        return true;
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $this->UnInstallEvents();
        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            'Удаление модуля ' . $this->MODULE_NAME,
            __DIR__ . '/unstep.php'
        );

        return true;
    }

    public function InstallEvents()
    {
        $eventManager = EventManager::getInstance();
        
        // Регистрируем обработчик на изменение сделки
        $eventManager->registerEventHandler(
            'crm',
            'OnAfterCrmDealUpdate',
            $this->MODULE_ID,
            '\Dellin\Integration\EventHandlers',
            'onDealUpdate'
        );

        return true;
    }

    public function UnInstallEvents()
    {
        $eventManager = EventManager::getInstance();
        
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnAfterCrmDealUpdate',
            $this->MODULE_ID,
            '\Dellin\Integration\EventHandlers',
            'onDealUpdate'
        );

        return true;
    }
}
?>