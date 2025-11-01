<?php
if (!check_bitrix_sessid()) return;

echo CAdminMessage::ShowNote('Модуль "Интеграция с Деловыми Линиями" успешно установлен');
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="submit" value="Вернуться">
</form>