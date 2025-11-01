<?php

namespace Dellin\Integration;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class BitrixHelper
{
    /**
     * Обновление полей сделки
     */
    public function updateDeal($dealId, $orderData)
    {
        try {
            if (!Loader::includeModule('crm')) {
                return false;
            }
            
            // Получаем коды полей из настроек
            $expectedDateField = Option::get('dellin.integration', 'field_expected_date', 'UF_CRM_EXPECTED_DATE');
            $weightField = Option::get('dellin.integration', 'field_weight', 'UF_CRM_CARGO_WEIGHT');
            $volumeField = Option::get('dellin.integration', 'field_volume', 'UF_CRM_CARGO_VOLUME');
            $placesField = Option::get('dellin.integration', 'field_places', 'UF_CRM_PLACES_COUNT');
            
            // Формируем данные для обновления
            $fields = [];
            
            // Конвертируем дату из формата YYYY-MM-DD в DD.MM.YYYY
            if (!empty($orderData['expected_arrival_date'])) {
                $date = $orderData['expected_arrival_date'];
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $dateObj = new \DateTime($date);
                    $fields[$expectedDateField] = $dateObj->format('d.m.Y');
                } else {
                    $fields[$expectedDateField] = $date;
                }
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
                return false;
            }
            
            // Обновляем сделку
            $deal = new \CCrmDeal(false);
            $result = $deal->Update($dealId, $fields);
            
            if ($result) {
                $this->log("✓ Сделка {$dealId} обновлена");
                return true;
            } else {
                $this->log("✗ Ошибка обновления сделки {$dealId}");
                return false;
            }
            
        } catch (\Throwable $e) {
            $this->log("✗ Ошибка: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Логирование
     */
    private function log($message)
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
            // Игнорируем
        }
    }
}
?>