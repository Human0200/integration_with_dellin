<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

defined('ADMIN_MODULE_NAME') or define('ADMIN_MODULE_NAME', 'dellin.integration');

if (!$USER->isAdmin()) {
    return;
}

$app = HttpApplication::getInstance();
$request = $app->getContext()->getRequest();

Loader::includeModule(ADMIN_MODULE_NAME);

$tabControl = new CAdminTabControl('tabControl', [
    [
        'DIV' => 'edit1',
        'TAB' => 'Настройки API',
        'TITLE' => 'Настройки подключения к API Деловых Линий'
    ],
    [
        'DIV' => 'edit2',
        'TAB' => 'Поля',
        'TITLE' => 'Настройки полей сделки'
    ],
    [
        'DIV' => 'edit3',
        'TAB' => 'Уведомления',
        'TITLE' => 'Настройки уведомлений'
    ]
]);

if ($request->isPost() && check_bitrix_sessid()) {
    
    // API настройки
    Option::set(ADMIN_MODULE_NAME, 'api_key', $request->getPost('api_key'));
    Option::set(ADMIN_MODULE_NAME, 'login', $request->getPost('login'));
    Option::set(ADMIN_MODULE_NAME, 'password', $request->getPost('password'));
    
    // Поля
    Option::set(ADMIN_MODULE_NAME, 'field_dellin_order', $request->getPost('field_dellin_order'));
    Option::set(ADMIN_MODULE_NAME, 'field_expected_date', $request->getPost('field_expected_date'));
    Option::set(ADMIN_MODULE_NAME, 'field_weight', $request->getPost('field_weight'));
    Option::set(ADMIN_MODULE_NAME, 'field_volume', $request->getPost('field_volume'));
    Option::set(ADMIN_MODULE_NAME, 'field_places', $request->getPost('field_places'));
    
    // Уведомления
    Option::set(ADMIN_MODULE_NAME, 'admin_email', $request->getPost('admin_email'));
    
    CAdminMessage::ShowNote('Настройки сохранены');
}

// Получаем текущие значения
$apiKey = Option::get(ADMIN_MODULE_NAME, 'api_key');
$login = Option::get(ADMIN_MODULE_NAME, 'login');
$password = Option::get(ADMIN_MODULE_NAME, 'password');

$fieldDellinOrder = Option::get(ADMIN_MODULE_NAME, 'field_dellin_order', 'UF_CRM_DELLIN_ORDER_ID');
$fieldExpectedDate = Option::get(ADMIN_MODULE_NAME, 'field_expected_date', 'UF_CRM_EXPECTED_DATE');
$fieldWeight = Option::get(ADMIN_MODULE_NAME, 'field_weight', 'UF_CRM_CARGO_WEIGHT');
$fieldVolume = Option::get(ADMIN_MODULE_NAME, 'field_volume', 'UF_CRM_CARGO_VOLUME');
$fieldPlaces = Option::get(ADMIN_MODULE_NAME, 'field_places', 'UF_CRM_PLACES_COUNT');

$adminEmail = Option::get(ADMIN_MODULE_NAME, 'admin_email');

?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode(ADMIN_MODULE_NAME) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    
    <?php $tabControl->Begin(); ?>
    
    <?php $tabControl->BeginNextTab(); ?>
    
    <tr class="heading">
        <td colspan="2"><b>Данные для подключения к API Деловых Линий</b></td>
    </tr>
    
    <tr>
        <td width="40%">API ключ: <span style="color: red;">*</span></td>
        <td width="60%">
            <input type="text" size="50" name="api_key" value="<?= htmlspecialcharsbx($apiKey) ?>" required>
            <br><small>Получить можно на <a href="https://dev.dellin.ru/registration/" target="_blank">https://dev.dellin.ru/registration/</a></small>
        </td>
    </tr>
    
    <tr>
        <td>Логин: <span style="color: red;">*</span></td>
        <td>
            <input type="text" size="50" name="login" value="<?= htmlspecialcharsbx($login) ?>" required>
        </td>
    </tr>
    
    <tr>
        <td>Пароль: <span style="color: red;">*</span></td>
        <td>
            <input type="password" size="50" name="password" value="<?= htmlspecialcharsbx($password) ?>" required>
        </td>
    </tr>
    
    <?php $tabControl->BeginNextTab(); ?>
    
    <tr class="heading">
        <td colspan="2"><b>Коды полей сделки</b></td>
    </tr>
    
    <tr>
        <td colspan="2">
            <div style="background: #e8f4fd; padding: 10px; border-left: 4px solid #2196F3; margin: 10px 0;">
                <strong>ℹ️ Как узнать код поля:</strong><br>
                1. Откройте любую сделку<br>
                2. Нажмите F12 (консоль разработчика)<br>
                3. Найдите нужное поле на странице<br>
                4. В HTML коде найдите атрибут <code>name</code> или <code>data-cid</code>
            </div>
        </td>
    </tr>
    
    <tr>
        <td width="40%">Поле "Номер заказа ДЛ": <span style="color: red;">*</span></td>
        <td width="60%">
            <input type="text" size="50" name="field_dellin_order" value="<?= htmlspecialcharsbx($fieldDellinOrder) ?>" required>
            <br><small>Если это поле заполнено в сделке - будет запрос к API ДЛ</small>
        </td>
    </tr>
    
    <tr>
        <td>Поле "Ожидаемая дата доставки":</td>
        <td>
            <input type="text" size="50" name="field_expected_date" value="<?= htmlspecialcharsbx($fieldExpectedDate) ?>">
            <br><small>Сюда запишется планируемая дата прихода заказа</small>
        </td>
    </tr>
    
    <tr>
        <td>Поле "Вес груза (кг)":</td>
        <td>
            <input type="text" size="50" name="field_weight" value="<?= htmlspecialcharsbx($fieldWeight) ?>">
        </td>
    </tr>
    
    <tr>
        <td>Поле "Объём груза (м³)":</td>
        <td>
            <input type="text" size="50" name="field_volume" value="<?= htmlspecialcharsbx($fieldVolume) ?>">
        </td>
    </tr>
    
    <tr>
        <td>Поле "Количество мест":</td>
        <td>
            <input type="text" size="50" name="field_places" value="<?= htmlspecialcharsbx($fieldPlaces) ?>">
        </td>
    </tr>
    
    <?php $tabControl->BeginNextTab(); ?>
    
    <tr>
        <td width="40%">Email для уведомлений об ошибках:</td>
        <td width="60%">
            <input type="email" size="50" name="admin_email" value="<?= htmlspecialcharsbx($adminEmail) ?>">
            <br><small>На этот email будут приходить уведомления о критических ошибках модуля</small>
        </td>
    </tr>
    
    <?php $tabControl->Buttons(); ?>
    
    <input type="submit" name="save" value="Сохранить" class="adm-btn-save">
    
    <?php $tabControl->End(); ?>
</form>

<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
    <h3>📋 Инструкция по использованию</h3>
    <ol>
        <li>Заполните все обязательные поля (отмечены <span style="color: red;">*</span>)</li>
        <li>Создайте в сделках поле "Номер заказа ДЛ" (тип: строка)</li>
        <li>Создайте поля для данных доставки (дата, числа)</li>
        <li>При заполнении номера заказа ДЛ в сделке - данные загрузятся автоматически</li>
    </ol>
    
    <p><strong>Логи модуля:</strong> <code>/local/logs/dellin_integration.log</code></p>
</div>