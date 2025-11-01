<?php

namespace Dellin\Integration;

use Bitrix\Main\Config\Option;
use Bitrix\Crm\DealTable;

class EventHandlers
{
    /**
     * Обработчик изменения сделки
     */
    public static function onDealUpdate(&$arFields)
    {
        try {
            // Получаем ID сделки
            $dealId = $arFields['ID'] ?? null;
            if (!$dealId) {
                return;
            }
            
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
                $deal = DealTable::getById($dealId)->fetch();
                $dellinOrderId = $deal[$dellinOrderField] ?? null;
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
            
            // Обновляем сделку
            $bitrixHelper = new BitrixHelper();
            $bitrixHelper->updateDeal($dealId, $orderData);
            
        } catch (\Throwable $e) {
            // Игнорируем все ошибки
        }
    }
}
?>