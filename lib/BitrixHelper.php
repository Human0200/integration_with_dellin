<?php

namespace Dellin\Integration;

use Bitrix\Main\Config\Option;
use Bitrix\Crm\DealTable;

class BitrixHelper
{
    /**
     * Обновление полей сделки
     */
    public function updateDeal($dealId, $orderData)
    {
        try {
            // Получаем коды полей из настроек
            $expectedDateField = Option::get('dellin.integration', 'field_expected_date', 'UF_CRM_EXPECTED_DATE');
            $weightField = Option::get('dellin.integration', 'field_weight', 'UF_CRM_CARGO_WEIGHT');
            $volumeField = Option::get('dellin.integration', 'field_volume', 'UF_CRM_CARGO_VOLUME');
            $placesField = Option::get('dellin.integration', 'field_places', 'UF_CRM_PLACES_COUNT');
            
            // Формируем данные для обновления
            $fields = [];
            
            if (!empty($orderData['expected_arrival_date'])) {
                $fields[$expectedDateField] = $orderData['expected_arrival_date'];
            }
            
            if (!empty($orderData['weight'])) {
                $fields[$weightField] = $orderData['weight'];
            }
            
            if (!empty($orderData['volume'])) {
                $fields[$volumeField] = $orderData['volume'];
            }
            
            if (!empty($orderData['places_count'])) {
                $fields[$placesField] = $orderData['places_count'];
            }
            
            if (empty($fields)) {
                $this->log("Нет данных для обновления сделки {$dealId}");
                return false;
            }
            
            // Обновляем сделку
            $result = DealTable::update($dealId, $fields);
            
            if ($result->isSuccess()) {
                $this->log("Сделка {$dealId} успешно обновлена: " . json_encode($fields));
                return true;
            } else {
                $errors = $result->getErrorMessages();
                $this->log("Ошибка обновления сделки {$dealId}: " . implode(', ', $errors));
                return false;
            }
            
        } catch (\Exception $e) {
            $this->log("Исключение при обновлении сделки {$dealId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Логирование
     */
    private function log($message)
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/bitrix_helper.log';
        
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}
?>