<?php
/**
 * ARGON.ru — сервер-реконструкция PartnerService API OZON.ru Android-приложения (2012).
 */

declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Helpers/Response.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Accounts.php';
require_once __DIR__ . '/../src/CartStore.php';
require_once __DIR__ . '/../src/Services/ClientService.php';
require_once __DIR__ . '/../src/Services/ItemGroupService.php';
require_once __DIR__ . '/../src/Services/SearchService.php';
require_once __DIR__ . '/../src/Services/ItemService.php';
require_once __DIR__ . '/../src/Services/DetailService.php';
require_once __DIR__ . '/../src/Services/PageFlowService.php';
require_once __DIR__ . '/../src/Services/CartService.php';

// --- Сбор параметров запроса (GET + тело POST, как оригинальный клиент шлёт querystring и в body) ---
$params = $_GET;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    if ($body !== false && $body !== '') {
        parse_str($body, $bodyParams);
        $params = array_merge($params, $bodyParams);
    }
    $params = array_merge($params, $_POST);
}

// --- Партнёрская авторизация (login=androidapp&password=..., см. Constants.AUTH) ---
$login = $params['login'] ?? '';
$password = $params['password'] ?? '';
if ($login !== Config::PARTNER_LOGIN || $password !== Config::PARTNER_PASSWORD) {
    Response::send(Response::error('PartnerAuth_Failed'), 403);
}

// --- Разбор пути вида /PartnerService/{Service}/{Method}/ ---
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = trim($path, '/');
$segments = $path === '' ? [] : explode('/', $path);

// Допускаем как /PartnerService/X/Y/, так и вызов напрямую /X/Y/
if (($segments[0] ?? '') === 'PartnerService') {
    array_shift($segments);
}

$serviceName = $segments[0] ?? '';
$methodName  = $segments[1] ?? '';

$routes = [
    'ClientService' => [
        'class' => ClientService::class,
        'methods' => [
            'ClientLogin'                 => 'clientLogin',
            'PartnerClientRegistration'   => 'partnerClientRegistration',
        ],
    ],
    'ItemGroupService' => [
        'class' => ItemGroupService::class,
        'methods' => [
            'CatalogsGet'          => 'catalogsGet',
            'CatalogsRecursiveGet' => 'catalogsRecursiveGet',
            'ItemsRecursiveGet'    => 'itemsRecursiveGet',
            'NewItemsGet'          => 'newItemsGet',
            'BestsellerItemsGet'       => 'bestsellerItemsGet',
            'RecommendItemsGet'    => 'recommendItemsGet',
        ],
    ],
    'SearchService' => [
        'class' => SearchService::class,
        'methods' => [
            'SearchItemsGet'       => 'searchItemsGet',
            'SearchSuggestionsGet' => 'searchSuggestionsGet',
        ],
    ],
    'ItemService' => [
        'class' => ItemService::class,
        'methods' => [
            'ItemGet'        => 'itemGet',
            'ItemGetByISBN'  => 'itemGetByIsbn',
            'ItemPriceGet'   => 'itemPriceGet',
        ],
    ],
    'DetailService' => [
        'class' => DetailService::class,
        'methods' => [
            'DetailGet'         => 'detailGet',
            'DetailRatingGet'   => 'detailRatingGet',
            'DetailCommentsGet' => 'detailCommentsGet',
        ],
    ],
    'PageFlowService' => [
        'class' => PageFlowService::class,
        'methods' => [
            'BreadCrumbsInfoGet' => 'breadCrumbsInfoGet',
            'ContextInfoGet' => 'contextInfoGet',
        ],
    ],
    'CartService' => [
        'class' => CartService::class,
        'methods' => [
            'CartGet'    => 'cartGet',
            'CartAdd'    => 'cartAdd',
            'CartRemove' => 'cartRemove',
            'CartModify' => 'cartModify',
        ],
    ],
];

if (!isset($routes[$serviceName]) || !isset($routes[$serviceName]['methods'][$methodName])) {
    Response::send(Response::error('Route_NotFound', ['Path' => $path]), 404);
}

$serviceClass = $routes[$serviceName]['class'];
$method = $routes[$serviceName]['methods'][$methodName];

try {
    $service = new $serviceClass();
    $result = $service->$method($params);
    Response::send($result);
} catch (Throwable $e) {
    Response::send(Response::error('Internal_Server_Error', ['Message' => $e->getMessage()]), 500);
}
