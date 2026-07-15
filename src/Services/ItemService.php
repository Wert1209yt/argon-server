<?php
/**
 * PartnerService/ItemService/*
 * ItemGet, ItemGetByISBN, ItemPriceGet
 */
final class ItemService
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /** GET ItemService/ItemGet/?id=... или ?detailId=... */
    public function itemGet(array $p): array
    {
        $id = (int)($p['id'] ?? $p['detailId'] ?? $p['itemId'] ?? 0);
        $item = $this->db->getItem($id);

        if (!$item) {
            return Response::error('Item_NotFound');
        }

        // Внедряем текст аннотации в корень объекта товара
        $text = (string)($item['Annotation'] ?? '');
        
        // Гарантируем, что все возможные вариации ключа описания заполнены
        $item['Annotation']  = $text;
        $item['Description'] = $text;
        $item['Text']        = $text;

        return Response::ok([
            // Старый Ozon ждёт объект товара либо в ключе 'Item', либо в 'GoodsItem'
            'Item'      => $item,
            'GoodsItem' => $item
        ]);
    }


    /** GET ItemGetByISBN/?isbn= */
    public function itemGetByIsbn(array $p): array
    {
        $isbn = (string)($p['isbn'] ?? '');
        $item = $this->db->getItemByIsbn($isbn);
        if ($item === null) {
            return Response::error('Item_GetByIsbn_NotFound', ['Item' => null]);
        }
        return Response::ok(['Item' => $item]);
    }

    /** GET ItemPriceGet/?itemId= */
    public function itemPriceGet(array $p): array
    {
        $id = (int)($p['itemId'] ?? $p['detailId'] ?? 0);
        $item = $this->db->getItem($id);
    
        // Получаем текущий Timestamp в миллисекундах (секунды * 1000)
        $timestampMs = time() * 1000; 
    
        // Оригинальный формат OzonDate строки для метода getOzonDateStr:
        $wcfDate = "/Date(" . $timestampMs . "+0300)/"; 
    
        $offsetMinutes = (int)(date('Z') / 60); 

        if ($item === null) {
            return Response::error([
                'ItemPrice' => [
                    'Id'                 => (string)$id,
                    'Price'              => "0",
                    'DiscountPrice'      => "0",
                    'Discount'           => "0",
                    'Ordered'            => false,
                    'ItemAvailabilityID' => 0,
                    'AvailabilityDate'   => [
                        'DateTime'      => $wcfDate, // Передаем WCF строку сюда!
                        'OffsetMinutes' => $offsetMinutes
                    ]
                ]
            ]);
        }

        $price = (string)round((float)($item['Price'] ?? 0));
        $discountPrice = (string)round((float)($item['DiscountPrice'] ?? $price));

        return Response::ok([
            'ItemPrice' => [
                'Id'                      => (string)$item['Id'],
                'Price'                   => $price,
                'DiscountPrice'           => $discountPrice,
                'DiscountPriceFromServer' => $discountPrice,
                'Discount'                => (string)($item['Discount'] ?? "0"),
                'Availability'            => (string)($item['Availability'] ?? "В наличии"),
                'DigitalTypeId'           => (string)($item['DigitalTypeId'] ?? "0"),
                'InSuite'                 => (string)($item['InSuite'] ?? "0"),
                'Weight'                  => (string)($item['Weight'] ?? "0"),
                'IsNew'                   => (bool)($item['IsNew'] ?? false),
                'IsSales'                 => (bool)($item['BargainSale'] ?? false),
                'IsSpecialPrice'          => (bool)($item['IsSpecialPrice'] ?? false),
                'Ordered'                 => (bool)($item['Ordered'] ?? false),
            
                'ItemAvailabilityID'      => (int)($item['ItemAvailabilityId'] ?? 1), 
                'itemAvailabilityId'      => (int)($item['ItemAvailabilityId'] ?? 1), 
                'ItemTypeID'              => (int)($item['ItemTypeId'] ?? 1),
                'itemTypeId'              => (int)($item['ItemTypeId'] ?? 1),
            
                'AvailabilityDate'        => [
                    'DateTime'      => $wcfDate,       // Теперь это "/Date(1783432800000+0300)/"
                    'OffsetMinutes' => $offsetMinutes  
                ]
            ]
        ]);
    }

}
