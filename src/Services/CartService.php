<?php
/**
 * PartnerService/CartService/*
 * CartGet, CartAdd, CartRemove, CartModify
 *
 * Корзина привязана к partnerClientId (guid устройства) — так же, как в оригинальном
 * протоколе, где OzonApplication.getGUID() передаётся во все запросы корзины.
 */
final class CartService
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /** GET CartGet/?partnerClientId= */
    public function cartGet(array $p): array
    {
        $guid = $p['partnerClientId'] ?? '';
        $store = new CartStore($guid);

        [$cartItems, $summary] = $this->buildCartItemsAndSummary($store);

        return Response::ok([
            // Списки товаров в корзине строго по декомпилированным полям Java
            'CartItems'             => $cartItems,
            'DelayedCartItems'      => [],
            'PreReleaseCartItems'   => [],
            'ProposalCartItems'     => [],
            
            // Вложенные объекты сводки цен и количеств, без которых Java падает в бесконечный цикл!
            'CartSummary'           => $summary,
            'PreReleaseCartSummary' => $summary,
        ]);

    }

    /** POST CartAdd/?partnerClientId=&cartItems=id:qty,id:qty */
    public function cartAdd(array $p): array
    {
        $guid = $p['partnerClientId'] ?? '';
        $store = new CartStore($guid);

        foreach ($this->parseCartItemsParam($p['cartItems'] ?? '') as [$itemId, $qty]) {
            if ($this->db->getItem($itemId) === null) {
                continue; // товар не найден — пропускаем
            }
            $store->addOrIncrement($itemId, $qty);
        }

        // CartAddResult extends SimpleOzonResult + Url
        return Response::ok(['Url' => null]);
    }

    /** POST CartRemove/?partnerClientId=&cartItems=id[,id...] */
    public function cartRemove(array $p): array
    {
        $guid = $p['partnerClientId'] ?? '';
        $store = new CartStore($guid);

        $raw = $p['cartItems'] ?? '';
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            // может прийти как просто id, так и id:qty
            $itemId = (int)explode(':', $part)[0];
            $store->remove($itemId);
        }

        return Response::ok();
    }

    /** POST CartModify/?partnerClientId=&itemId=&quantity= */
    public function cartModify(array $p): array
    {
        $guid = $p['partnerClientId'] ?? '';
        $itemId = (int)($p['itemId'] ?? 0);
        $quantity = (int)($p['quantity'] ?? 0);

        $store = new CartStore($guid);
        if ($quantity <= 0) {
            $store->remove($itemId);
        } else {
            $store->setQuantity($itemId, $quantity);
        }

        return Response::ok();
    }

    /** @return array{0: array, 1: array} */
    private function buildCartItemsAndSummary(CartStore $store): array
    {
        $cartItems = [];
        $totalSum = 0.0;
        $totalWeight = 0;
        $totalQty = 0;

        foreach ($store->getItems() as $itemId => $qty) {
            $item = $this->db->getItem($itemId);
            if ($item === null) {
                continue;
            }
            $clientPrice = $item['DiscountPrice'] ?? $item['Price'];
            $cartItems[] = [
                'ItemId'              => $item['Id'],
                'Name'                => $item['Name'],
                'Author'              => $item['Author'],
                'OtherName'           => $item['OtherName'],
                'Path'                => $item['Path'],
                'ItemType'            => $item['ItemType'],
                'ItemTypeId'          => $item['ItemTypeId'],
                'Availability'        => $item['Availability'],
                'ItemAvailabilityId'  => $item['ItemAvailabilityId'],
                'Price'               => $item['Price'],
                'PriceCurrency'       => 'RUR',
                'ClientPrice'         => $clientPrice,
                'ClientPriceCurrency' => 'RUR',
                'Discount'            => $item['Discount'],
                'SpecialPrice'        => $item['IsSpecialPrice'] ? $clientPrice : null,
                'Quantity'            => $qty,
                'RequiredQty'         => $qty,
                'ExemplarQty'         => 1,
                'SuiteId'             => 0,
                'SuiteCount'          => 0,
                'Weight'              => $item['Weight'],
                'IsDelayed'           => false,
                'ShowOrder'           => true,
            ];
            $totalSum += (float)$clientPrice * $qty;
            $totalWeight += (int)$item['Weight'] * $qty;
            $totalQty += $qty;
        }

        // Формируем сводку, которая на 100% совместима с Java-классом CartSummary
        $summary = [
            'Sum'            => number_format($totalSum, 2, '.', ''),
            'FullSum'        => number_format($totalSum, 2, '.', ''),
            'Discount'       => '0',
            'FullWeight'     => (string)$totalWeight,
            'ItemQty'        => (string)$totalQty,
            'ClientAccount'  => '0',
            
            // Заменяем null на пустую строку, чтобы setDoneMinDate(String) не падал
            'DoneMinDate'    => '', 
            
            // Если Java спотыкается на double/float в JSON, отдаем как чистые числа 0
            // Либо, если это не поможет, парсер может требовать их как String "0.0" 
            // (дублируем в camelCase и PascalCase для безопасности)
            'ScoreToAdd'     => 0,
            'scoreToAdd'     => 0,
            'ScoreToPay'     => 0,
            'scoreToPay'     => 0,
            'ScoreValue'     => 0,
            'scoreValue'     => 0,
        ];

        return [$cartItems, $summary];
    }

    /** "12:3,45:1" -> [[12,3],[45,1]] ; поддерживает так же "12,3" на всякий случай */
    private function parseCartItemsParam(string $raw): array
    {
        $result = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $sep = str_contains($part, ':') ? ':' : ';';
            $bits = explode($sep, $part);
            $itemId = (int)($bits[0] ?? 0);
            $qty = isset($bits[1]) ? (int)$bits[1] : 1;
            if ($itemId > 0 && $qty > 0) {
                $result[] = [$itemId, $qty];
            }
        }
        return $result;
    }
}
