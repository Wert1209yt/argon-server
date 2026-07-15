<?php
/**
 * Работа с товарной базой db.xml: категории и товары.
 */
final class Db
{
    private SimpleXMLElement $xml;

    public function __construct(string $file = Config::DB_FILE)
    {
        if (!file_exists($file)) {
            throw new RuntimeException("db.xml не найден: $file");
        }
        $this->xml = simplexml_load_file($file);
        if ($this->xml === false) {
            throw new RuntimeException('Не удалось разобрать db.xml');
        }
    }

    /** Все категории верхнего уровня (parentId=0), либо дочерние для заданного id/имени. */
    public function getCategories(?int $parentId = null): array
    {
        $result = [];
        foreach ($this->xml->categories->category as $cat) {
            $pid = (int)$cat['parentId'];
            if ($parentId === null || $pid === $parentId) {
                $result[] = $this->categoryToArray($cat);
            }
        }
        return $result;
    }

    public function getAllCategories(): array
    {
        $result = [];
        foreach ($this->xml->categories->category as $cat) {
            $result[] = $this->categoryToArray($cat);
        }
        return $result;
    }

    private function categoryToArray(SimpleXMLElement $cat): array
    {
        return [
            'Id'              => (int)$cat['id'],
            'ParentID'        => (int)$cat['parentId'],
            'Name'            => (string)$cat['name'],
            'WebSectionName'  => (string)$cat['webSectionName'],
            'Weight'          => (int)$cat['weight'],
        ];
    }

    public function findCategoryIdByWebSectionName(string $name): ?int
    {
        foreach ($this->xml->categories->category as $cat) {
            if ((string)$cat['webSectionName'] === $name) {
                return (int)$cat['id'];
            }
        }
        return null;
    }

    /** Возвращает id категории и всех её потомков (рекурсивно) — для ItemsRecursiveGet. */
    public function getCategoryIdsRecursive(int $rootId): array
    {
        $ids = [$rootId];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($this->xml->categories->category as $cat) {
                $id = (int)$cat['id'];
                $pid = (int)$cat['parentId'];
                if (in_array($pid, $ids, true) && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $changed = true;
                }
            }
        }
        return $ids;
    }

    public function getCategoryById(int $id): ?array
    {
        foreach ($this->xml->categories->category as $cat) {
            if ((int)$cat['id'] === $id) {
                return $this->categoryToArray($cat);
            }
        }
        return null;
    }

    public function hasChildren(int $parentId): bool
    {
        foreach ($this->xml->categories->category as $cat) {
            if ((int)$cat['parentId'] === $parentId) {
                return true;
            }
        }
        return false;
    }

    /** Все товары, опционально отфильтрованные по списку id категорий. */
    public function getItems(?array $categoryIds = null, ?bool $onlyNew = null): array
    {
        $result = [];
        foreach ($this->xml->items->item as $item) {
            $catId = (int)$item['categoryId'];
            if ($categoryIds !== null && !in_array($catId, $categoryIds, true)) {
                continue;
            }
            if ($onlyNew !== null && $this->boolAttr($item, 'isNew') !== $onlyNew) {
                continue;
            }
            $result[] = $this->itemToGoodItem($item);
        }
        return $result;
    }

    public function getItem(int $id): ?array
    {
        foreach ($this->xml->items->item as $item) {
            if ((int)$item['id'] === $id) {
                return $this->itemToGoodItem($item);
            }
        }
        return null;
    }

    public function getItemByIsbn(string $isbn): ?array
    {
        foreach ($this->xml->items->item as $item) {
            if ((string)$item['isbn'] !== '' && (string)$item['isbn'] === $isbn) {
                return $this->itemToGoodItem($item);
            }
        }
        return null;
    }

    /** Простой полнотекстовый поиск по названию/автору (регистронезависимый). */
    public function searchItems(string $text, ?int $categoryId = null): array
    {
        $needle = mb_strtolower(trim($text));
        $result = [];
        if ($needle === '') {
            return $result;
        }
        $catIds = $categoryId !== null ? $this->getCategoryIdsRecursive($categoryId) : null;
        foreach ($this->xml->items->item as $item) {
            if ($catIds !== null && !in_array((int)$item['categoryId'], $catIds, true)) {
                continue;
            }
            $haystack = mb_strtolower((string)$item['name'] . ' ' . (string)$item['author']);
            if (mb_strpos($haystack, $needle) !== false) {
                $result[] = $this->itemToGoodItem($item);
            }
        }
        return $result;
    }

    public function suggestItems(string $text, int $limit = 10): array
    {
        $needle = mb_strtolower(trim($text));
        $result = [];
        if ($needle === '') {
            return $result;
        }
        foreach ($this->xml->items->item as $item) {
            $name = (string)$item['name'];
            if (mb_strpos(mb_strtolower($name), $needle) !== false) {
                $result[] = $name;
                if (count($result) >= $limit) {
                    break;
                }
            }
        }
        return $result;
    }

    private function boolAttr(SimpleXMLElement $item, string $name): bool
    {
        $v = strtolower((string)$item[$name]);
        return $v === 'true' || $v === '1';
    }

    /**
     * Преобразует <item> XML в структуру, совпадающую с полями
     * Models/Remote/GoodItem.java (+ вложенный Detail/ItemType).
     */
    private function itemToGoodItem(SimpleXMLElement $item): array
    {
        $id = (int)$item['id'];
        $categoryId = (int)$item['categoryId'];
        $path = (string)$item['path'];

        return [
            'Id'                 => $id,
            'Name'               => (string)$item['name'],
            'Author'             => (string)$item['author'],
            'Annotation'         => (string)$item['annotation'],
            'Price'              => (string)$item['price'],
            'DiscountPrice' => (string)$item['discountPrice'] !== '' ? (string)$item['discountPrice'] : (string)$item['price'],
            'Discount'           => $this->computeDiscount($item),
            'ItemType'           => (string)$item['itemType'],
            'ItemTypeId'         => (int)$item['itemTypeId'],
            'Availability'       => (string)$item['availability'],
            'ItemAvailabilityId' => (int)$item['itemAvailabilityId'],
            'IsNew'              => $this->boolAttr($item, 'isNew'),
            'IsSpecialPrice'     => $this->boolAttr($item, 'isSpecialPrice'),
            'BargainSale'        => $this->boolAttr($item, 'bargainSale'),
            'Year'               => (int)$item['year'],
            'Weight'             => (int)$item['weight'],
            'Path'               => Config::MEDIA_BASE_URL . $path,
            'ClientRating'       => (string)$item['clientRating'],
            'ClientRatingCount'  => (int)$item['clientRatingCount'],
            'OtherName'          => (string)$item['otherName'],
            'Media'              => (string)$item['media'],
            'InSuite'            => 0,
            'DigitalTypeId'      => 0,
            'LookInSide'         => false,
            'Ordered'            => false,
            'Isbn'               => (string)$item['isbn'],
            // Вложенная деталь товара (Detail.java) — id детали = id товара
            'Detail' => [
                'Id'            => $id,
                'BranchId'      => $categoryId,
                'DestinationId' => $categoryId,
                'ViewId'        => $id,
                'ItemType' => [
                    'Id'    => (int)$item['itemTypeId'],
                    'Name'  => (string)$item['itemType'],
                    'Brief' => (string)$item['itemType'],
                ],
                'ClassAttributes' => $this->buildClassAttributes($item),
            ],
        ];
    }

    private function computeDiscount(SimpleXMLElement $item): string
    {
        $price = (float)$item['price'];
        $discountPrice = (string)$item['discountPrice'];
        if ($price <= 0 || $discountPrice === '') {
            return '0';
        }
        $discount = round((1 - ((float)$discountPrice / $price)) * 100);
        return (string)max(0, $discount);
    }

    private function buildClassAttributes(SimpleXMLElement $item): array
    {
        $attributes = [];
        $idCounter = 1;

        // 1. Возвращаем оригинальную логику сбора характеристик.
    
        if ((string)$item['author'] !== '') {
            $attributes[] = ['Id' => $idCounter++, 'Name' => 'Автор', 'Tag' => 'author', 'Value' => (string)$item['author']];
        }
        if ((string)$item['year'] !== '0' && (string)$item['year'] !== '') {
            $attributes[] = ['Id' => $idCounter++, 'Name' => 'Год издания', 'Tag' => 'year', 'Value' => (string)$item['year']];
        }
        if ((string)$item['isbn'] !== '') {
            $attributes[] = ['Id' => $idCounter++, 'Name' => 'ISBN', 'Tag' => 'isbn', 'Value' => (string)$item['isbn']];
        }
        if ((string)$item['otherName'] !== '') {
            $attributes[] = ['Id' => $idCounter++, 'Name' => 'Модель', 'Tag' => 'otherName', 'Value' => (string)$item['otherName']];
        }
        if ((string)$item['media'] !== '') {
            $attributes[] = ['Id' => $idCounter++, 'Name' => 'Оформление', 'Tag' => 'media', 'Value' => (string)$item['media']];
        }

        $annotationText = (string)$item['annotation'];
        if ($annotationText !== '') {
            $attributes[] = [
                'Id'    => $idCounter++,
                'Name'  => 'Описание',       
                'Tag'   => 'annotation',
                'Value' => $annotationText   
            ];
            $attributes[] = [
                'Id'    => $idCounter++,
                'Name'  => 'Аннотация',
                'Tag'   => 'description',
                'Value' => $annotationText
            ];
         }

        return $attributes;
    }

}
