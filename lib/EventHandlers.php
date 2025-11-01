<?php

namespace Dellin\Integration;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class EventHandlers
{
    public static function onDealUpdate(&$arFields)
    {
        try {
            // Используем глобальную переменную для защиты от повторных вызовов
            global $DELLIN_PROCESSING;
            if (!isset($DELLIN_PROCESSING)) {
                $DELLIN_PROCESSING = [];
            }
            
            if (!Loader::includeModule('crm')) {
                return;
            }
            
            $dealId = $arFields['ID'] ?? null;
            if (!$dealId) {
                return;
            }
            
            // Защита от повторного срабатывания
            if (isset($DELLIN_PROCESSING[$dealId])) {
                return;
            }
            
            $DELLIN_PROCESSING[$dealId] = true;
            
            // Получаем настройки
            $apiKey = Option::get('dellin.integration', 'api_key');
            $login = Option::get('dellin.integration', 'login');
            $password = Option::get('dellin.integration', 'password');
            
            if (empty($apiKey) || empty($login) || empty($password)) {
                return;
            }
            
            // Получаем номер заказа ДЛ
            $dellinOrderField = Option::get('dellin.integration', 'field_dellin_order', 'UF_CRM_DELLIN_ORDER_ID');
            $dellinOrderId = $arFields[$dellinOrderField] ?? null;
            
            // Если номера заказа нет в изменениях - загружаем из базы
            if (empty($dellinOrderId)) {
                $dbDeal = \CCrmDeal::GetListEx(
                    [],
                    ['ID' => $dealId, 'CHECK_PERMISSIONS' => 'N'],
                    false,
                    false,
                    ['*', 'UF_*']
                );
                
                if ($deal = $dbDeal->Fetch()) {
                    $dellinOrderId = $deal[$dellinOrderField] ?? null;
                }
            }
            
            if (empty($dellinOrderId)) {
                return;
            }
            
            // Получаем данные из ДЛ
            $dellinApi = new DellinApi($apiKey, $login, $password);
            $orderData = $dellinApi->getOrderInfo($dellinOrderId);
            
            if (!$orderData) {
                return;
            }
            
            // Обновляем сделку (внутри BitrixHelper проверка на изменения)
            $bitrixHelper = new BitrixHelper();
            $bitrixHelper->updateDeal($dealId, $orderData);
            
        } catch (\Throwable $e) {
            // Игнорируем все ошибки
        }
    }
}
?>