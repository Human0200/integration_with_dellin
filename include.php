<?php

namespace Dellin\Integration;

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Crm\DealTable;

Loader::registerAutoLoadClasses(
    'dellin.integration',
    [
        'Dellin\Integration\DellinApi' => 'lib/DellinApi.php',
        'Dellin\Integration\BitrixHelper' => 'lib/BitrixHelper.php',
        'Dellin\Integration\EventHandlers' => 'lib/EventHandlers.php',
    ]
);

/**
 * Класс обработчиков событий
 */
class EventHandlers
{
    /**
     * Обработчик изменения сделки
     * Срабатывает только если заполнен номер заказа ДЛ
     */
    public static function onDealUpdate(&$arFields)
    {
        // Оборачиваем ВСЁ в try-catch, чтобы любая ошибка не упала сайт
        try {
            // Проверяем, что модуль подключен
            if (!Loader::includeModule('dellin.integration')) {
                return;
            }
            
            // Получаем ID сделки
            $dealId = $arFields['ID'] ?? null;
            
            if (!$dealId) {
                return; // Нет ID - выходим тихо
            }
            
            // Получаем настройки модуля
            $apiKey = Option::get('dellin.integration', 'api_key');
            $login = Option::get('dellin.integration', 'login');
            $password = Option::get('dellin.integration', 'password');
            
            // Если модуль не настроен - выходим тихо
            if (empty($apiKey) || empty($login) || empty($password)) {
                self::log('Модуль не настроен. Пропуск обработки.');
                return;
            }
            
            // Получаем код поля с номером заказа ДЛ
            $dellinOrderField = Option::get('dellin.integration', 'field_dellin_order', 'UF_CRM_DELLIN_ORDER_ID');
            
            // Проверяем, что поле номера заказа ДЛ изменилось или уже заполнено
            $dellinOrderId = null;
            
            // Сначала смотрим в изменяемых полях
            if (isset($arFields[$dellinOrderField]) && !empty($arFields[$dellinOrderField])) {
                $dellinOrderId = $arFields[$dellinOrderField];
            } else {
                // Если в текущих изменениях нет - загружаем сделку целиком
                $dealResult = DealTable::getById($dealId);
                if ($dealResult) {
                    $deal = $dealResult->fetch();
                    if ($deal && isset($deal[$dellinOrderField]) && !empty($deal[$dellinOrderField])) {
                        $dellinOrderId = $deal[$dellinOrderField];
                    }
                }
            }
            
            // Если номера заказа нет - выходим тихо
            if (empty($dellinOrderId)) {
                return;
            }
            
            self::log("Обнаружен номер заказа ДЛ для сделки {$dealId}: {$dellinOrderId}");
            
            // Запускаем обработку в фоне через агента для безопасности
            \CAgent::AddAgent(
                "\Dellin\Integration\EventHandlers::processDellinUpdate({$dealId}, '{$dellinOrderId}');",
                'dellin.integration',
                'N',
                0,
                '',
                'Y',
                \ConvertTimeStamp(time() + 5, 'FULL')
            );
            
        } catch (\Throwable $e) {
            // Ловим ВСЕ возможные ошибки (Exception и Error)
            // Логируем, но НЕ прокидываем дальше
            self::log("КРИТИЧЕСКАЯ ОШИБКА в onDealUpdate: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            // Отправляем уведомление админу (опционально)
            self::notifyAdmin("Ошибка в модуле Dellin Integration: " . $e->getMessage());
            
            // НЕ возвращаем ошибку - просто выходим
            return;
        }
    }
    
    /**
     * Фоновая обработка обновления данных из ДЛ
     * Выполняется через агента для максимальной безопасности
     */
    public static function processDellinUpdate($dealId, $dellinOrderId)
    {
        try {
            // Получаем настройки
            $apiKey = Option::get('dellin.integration', 'api_key');
            $login = Option::get('dellin.integration', 'login');
            $password = Option::get('dellin.integration', 'password');
            
            // Создаем экземпляр API ДЛ
            $dellinApi = new DellinApi($apiKey, $login, $password);
            
            // Получаем данные заказа из ДЛ
            $orderData = $dellinApi->getOrderInfo($dellinOrderId);
            
            if (!$orderData) {
                self::log("Не удалось получить данные заказа {$dellinOrderId} из ДЛ для сделки {$dealId}");
                return ''; // Возвращаем пустую строку - агент удалится
            }
            
            // Обновляем сделку
            $bitrixHelper = new BitrixHelper();
            $result = $bitrixHelper->updateDeal($dealId, $orderData);
            
            if ($result) {
                self::log("Сделка {$dealId} успешно обновлена данными из ДЛ");
            } else {
                self::log("Ошибка обновления сделки {$dealId}");
            }
            
        } catch (\Throwable $e) {
            self::log("ОШИБКА в processDellinUpdate для сделки {$dealId}: " . $e->getMessage());
        }
        
        // Возвращаем пустую строку - агент выполнится один раз и удалится
        return '';
    }
    
    /**
     * Логирование
     */
    private static function log($message)
    {
        try {
            $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/dellin_integration.log';
            
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // Даже если логирование упадет - ничего не делаем
        }
    }
    
    /**
     * Уведомление администратора об ошибке
     */
    private static function notifyAdmin($message)
    {
        try {
            // Получаем email администратора из настроек
            $adminEmail = Option::get('dellin.integration', 'admin_email');
            
            if (!empty($adminEmail)) {
                @mail(
                    $adminEmail,
                    'Ошибка в модуле Dellin Integration',
                    $message . "\n\nВремя: " . date('Y-m-d H:i:s'),
                    "From: noreply@" . $_SERVER['HTTP_HOST']
                );
            }
        } catch (\Throwable $e) {
            // Если даже отправка письма упала - молча игнорируем
        }
    }
}
?>