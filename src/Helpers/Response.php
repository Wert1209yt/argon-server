<?php
/**
 * Ответы сервера сериализуются в JSON с PascalCase-ключами — так же, как Gson
 * сериализует поля Java-классов RemoteResults/*.java на реальном ows.ozon.ru.
 *
 * Status = 2  → Constants.SERVICE_RESULT_OK (успех, как проверяет ApiHelper/клиент)
 * Status = 1  → ошибка
 */
final class Response
{
    public static function ok(array $extra = []): array
    {
        return array_merge(['Status' => 2, 'Error' => null], $extra);
    }

    public static function error(string $errorCode, array $extra = []): array
    {
        return array_merge(['Status' => 1, 'Error' => ['ErrorCode' => $errorCode]], $extra);
    }

    public static function send(array $payload, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
