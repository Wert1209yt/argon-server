<?php
/**
 * Роутер для встроенного веб-сервера PHP:
 *   php -S localhost:8000 router.php
 *
 * Все запросы (в т.ч. /PartnerService/...) перенаправляются в public/index.php,
 * т.к. таких файлов физически не существует — это виртуальные REST-маршруты.
 */
file_put_contents('php://stdout', sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI']));

require __DIR__ . '/public/index.php';
