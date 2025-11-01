<?php

namespace Dellin\Integration;

class DellinApi
{
    private $baseUrl = 'https://api.dellin.ru';
    private $apiKey;
    private $login;
    private $password;
    private $sessionId = null;
    private $timeout = 10; // Таймаут запросов в секундах
    
    public function __construct($apiKey, $login, $password)
    {
        $this->apiKey = $apiKey;
        $this->login = $login;
        $this->password = $password;
    }
    
    /**
     * Авторизация и получение sessionID
     */
    public function authorize()
    {
        try {
            $url = $this->baseUrl . '/v3/auth/login.json';
            
            $payload = [
                'appKey' => $this->apiKey,
                'login' => $this->login,
                'password' => $this->password
            ];
            
            $response = $this->makeRequest($url, $payload);
            
            if ($response && isset($response['data']['sessionID'])) {
                $this->sessionId = $response['data']['sessionID'];
                $this->log("Авторизация успешна");
                return true;
            } else {
                $this->log("Ошибка авторизации: " . json_encode($response));
                return false;
            }
        } catch (\Throwable $e) {
            $this->log("Исключение при авторизации: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Получение информации о заказе из ДЛ
     */
    public function getOrderInfo($orderId)
    {
        try {
            if (!$this->sessionId) {
                if (!$this->authorize()) {
                    return null;
                }
            }
            
            $url = $this->baseUrl . '/v3/orders.json';
            
            $payload = [
                'appKey' => $this->apiKey,
                'sessionID' => $this->sessionId,
                'docIds' => [$orderId]
            ];
            
            $response = $this->makeRequest($url, $payload);
            
            if ($response && isset($response['orders']) && count($response['orders']) > 0) {
                $order = $response['orders'][0];
                return $this->parseOrderData($order);
            } else {
                $this->log("Заказ не найден: " . $orderId);
                return null;
            }
        } catch (\Throwable $e) {
            $this->log("Исключение при получении заказа {$orderId}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Парсинг нужных данных из заказа
     */
    private function parseOrderData($order)
    {
        $orderData = [
            'expected_arrival_date' => null,
            'weight' => null,
            'volume' => null,
            'places_count' => null
        ];
        
        try {
            // Ожидаемая дата прихода
            if (isset($order['delivery']['arrival']['planDate'])) {
                $orderData['expected_arrival_date'] = $order['delivery']['arrival']['planDate'];
            }
            
            // Вес, объём и количество мест
            if (isset($order['cargo'])) {
                $cargo = $order['cargo'];
                
                if (isset($cargo['weight'])) {
                    $orderData['weight'] = floatval($cargo['weight']);
                }
                
                if (isset($cargo['volume'])) {
                    $orderData['volume'] = floatval($cargo['volume']);
                }
                
                if (isset($cargo['quantity'])) {
                    $orderData['places_count'] = intval($cargo['quantity']);
                } elseif (isset($cargo['places']) && is_array($cargo['places'])) {
                    $orderData['places_count'] = count($cargo['places']);
                }
            }
            
            return $orderData;
            
        } catch (\Throwable $e) {
            $this->log("Ошибка парсинга данных заказа: " . $e->getMessage());
            return $orderData;
        }
    }
    
    /**
     * Выполнение HTTP запроса с защитой от таймаутов
     */
    private function makeRequest($url, $data)
    {
        try {
            $ch = curl_init($url);
            
            if ($ch === false) {
                throw new \Exception("Не удалось инициализировать cURL");
            }
            
            $headers = ['Content-Type: application/json'];
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($response === false) {
                throw new \Exception("cURL error: " . $error);
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("JSON decode error: " . json_last_error_msg());
                }
                return $decoded;
            } else {
                $this->log("HTTP {$httpCode}: " . substr($response, 0, 200));
                return null;
            }
            
        } catch (\Throwable $e) {
            $this->log("Ошибка запроса: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Безопасное логирование
     */
    private function log($message)
    {
        try {
            $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/dellin_api.log';
            
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // Молча игнорируем ошибки логирования
        }
    }
}
?>