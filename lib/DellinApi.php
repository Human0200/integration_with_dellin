<?php

namespace Dellin\Integration;

class DellinApi
{
    private $baseUrl = 'https://api.dellin.ru';
    private $apiKey;
    private $login;
    private $password;
    private $sessionId = null;
    private $timeout = 10;
    
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
                return true;
            } else {
                $this->log("Ошибка авторизации в API ДЛ");
                return false;
            }
        } catch (\Throwable $e) {
            $this->log("Ошибка авторизации: " . $e->getMessage());
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
            
            // Пробуем найти по orderNumber
            $payload = [
                'appKey' => $this->apiKey,
                'sessionID' => $this->sessionId,
                'orderNumber' => $orderId
            ];
            
            $response = $this->makeRequest($url, $payload);
            
            if ($response && isset($response['orders']) && count($response['orders']) > 0) {
                $this->log("✓ Заказ {$orderId} найден");
                return $this->parseOrderData($response['orders'][0]);
            }
            
            // Если не нашли по orderNumber, пробуем по docIds
            $payload = [
                'appKey' => $this->apiKey,
                'sessionID' => $this->sessionId,
                'docIds' => [$orderId]
            ];
            
            $response = $this->makeRequest($url, $payload);
            
            if ($response && isset($response['orders']) && count($response['orders']) > 0) {
                $this->log("✓ Заказ {$orderId} найден");
                return $this->parseOrderData($response['orders'][0]);
            }
            
            // Пробуем найти по orderId
            $payload = [
                'appKey' => $this->apiKey,
                'sessionID' => $this->sessionId,
                'orderId' => $orderId
            ];
            
            $response = $this->makeRequest($url, $payload);
            
            if ($response && isset($response['orders']) && count($response['orders']) > 0) {
                $this->log("✓ Заказ {$orderId} найден");
                return $this->parseOrderData($response['orders'][0]);
            }
            
            $this->log("✗ Заказ {$orderId} не найден");
            return null;
            
        } catch (\Throwable $e) {
            $this->log("Ошибка получения заказа {$orderId}: " . $e->getMessage());
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
            if (isset($order['orderDates']['arrivalToOspReceiver'])) {
                $orderData['expected_arrival_date'] = $order['orderDates']['arrivalToOspReceiver'];
            } elseif (isset($order['orderDates']['giveoutFromOspReceiverMax'])) {
                $orderData['expected_arrival_date'] = $order['orderDates']['giveoutFromOspReceiverMax'];
            } elseif (isset($order['orderDates']['giveoutFromOspReceiver'])) {
                $orderData['expected_arrival_date'] = $order['orderDates']['giveoutFromOspReceiver'];
            } elseif (isset($order['air']['arrivalDate'])) {
                $orderData['expected_arrival_date'] = $order['air']['arrivalDate'];
            }
            
            // Вес, объём и количество мест
            if (isset($order['freight'])) {
                $freight = $order['freight'];
                
                if (isset($freight['weight'])) {
                    $orderData['weight'] = floatval($freight['weight']);
                }
                
                if (isset($freight['volume'])) {
                    $orderData['volume'] = floatval($freight['volume']);
                }
                
                if (isset($freight['places'])) {
                    $orderData['places_count'] = intval($freight['places']);
                }
            }
            
            // Если не нашли количество мест в freight, пробуем cargoPlaces
            if (empty($orderData['places_count']) && isset($order['cargoPlaces']) && is_array($order['cargoPlaces'])) {
                $totalPlaces = 0;
                foreach ($order['cargoPlaces'] as $place) {
                    if (isset($place['amount'])) {
                        $totalPlaces += intval($place['amount']);
                    }
                }
                if ($totalPlaces > 0) {
                    $orderData['places_count'] = $totalPlaces;
                }
            }
            
            return $orderData;
            
        } catch (\Throwable $e) {
            $this->log("Ошибка парсинга данных заказа: " . $e->getMessage());
            return $orderData;
        }
    }
    
    /**
     * Выполнение HTTP запроса
     */
    private function makeRequest($url, $data)
    {
        try {
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($data),
                    'timeout' => $this->timeout,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new \Exception("Не удалось выполнить запрос");
            }
            
            // Получаем HTTP код
            $httpCode = 200;
            if (isset($http_response_header[0])) {
                preg_match('/\d{3}/', $http_response_header[0], $matches);
                if (isset($matches[0])) {
                    $httpCode = intval($matches[0]);
                }
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Ошибка декодирования JSON");
                }
                return $decoded;
            }
            
            return null;
            
        } catch (\Throwable $e) {
            $this->log("Ошибка запроса: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Логирование
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
            // Игнорируем ошибки логирования
        }
    }
}
?>