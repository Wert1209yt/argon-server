<?php
/**
 * PartnerService/ItemGroupService/*
 * CatalogsGet, CatalogsRecursiveGet, ItemsRecursiveGet, NewItemsGet, BestsellersGet(basic), RecommendItemsGet(basic)
 */
final class ItemGroupService
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /** GET CatalogsGet/?startItemGroupId=&startGroupName=&sortTags= */
    public function catalogsGet(array $p): array
    {
        $groupId = $this->resolveGroupId($p);

        // Корень каталога
        if ($groupId === null) {
            return Response::ok([
                'CatalogItems' => $this->db->getCategories(0),
                'GoodsItems'   => [],
                'Items'        => [],
                'TotalItems'   => 0
            ]);
        }

        // Получаем дочерние категории
        $subCategories = $this->db->getCategories($groupId);

        // Если есть подкатегории — возвращаем их
        if (!empty($subCategories)) {
            return Response::ok([
                'CatalogItems' => array_values($subCategories),
                'GoodsItems'   => [],
                'Items'        => [],
                'TotalItems'   => count($subCategories)
            ]);
        }

        // Конечная категория.
        $category = $this->db->getCategoryById($groupId);

        if ($category === null) {
            return Response::ok([
                'CatalogItems' => [],
                'GoodsItems'   => [],
                'Items'        => [],
                'TotalItems'   => 0
            ]);
        }

        // Считаем товары для отображения количества
        $categoryIds = $this->db->getCategoryIdsRecursive($groupId);
        $goods = $this->db->getItems($categoryIds);

        $category['Weight'] = count($goods);

        return Response::ok([
            'CatalogItems' => [$category],
            'GoodsItems'   => [],
            'Items'        => [],
            'TotalItems'   => count($goods)
        ]);
    }

    public function catalogsRecursiveGet(array $p): array
    {
        // Для простоты возвращает полное дерево категорий
        return Response::ok(['CatalogItems' => $this->db->getAllCategories()]);
    }

    /** GET ItemsRecursiveGet/?startItemGroupId=&startGroupName=&itemsOnPage=&pageNumber= */
    public function itemsRecursiveGet(array $p): array
    {
        $groupId = $this->resolveGroupId($p);
        if ($groupId === null) {
            return Response::error('ItemGroup_ItemsRecursive_GetFailed', ['GoodsItems' => [], 'TotalItems' => 0]);
        }

        $categoryIds = $this->db->getCategoryIdsRecursive($groupId);
        $allItems = $this->db->getItems($categoryIds);

        [$pageItems, $total] = $this->paginate($allItems, $p);

        return Response::ok(['GoodsItems' => $pageItems, 'TotalItems' => $total]);
    }

    /** GET NewItemsGet/?rootId=&responseTags=&groupName=&itemsOnPage=&pageNumber= */
    public function newItemsGet(array $p): array
    {
        $groupId = $this->resolveGroupId($p);
        $categoryIds = $groupId !== null ? $this->db->getCategoryIdsRecursive($groupId) : null;
        $allItems = $this->db->getItems($categoryIds, true);

        [$pageItems, $total] = $this->paginate($allItems, $p);

        return Response::ok(['Items' => $pageItems, 'TotalItems' => $total]);
    }

    /** GET RecommendItemsGet/ */
    public function recommendItemsGet(array $p): array
    {
        $items = array_values(array_filter($this->db->getItems(), fn($i) => $i['IsSpecialPrice']));
        return Response::ok(['Items' => array_slice($items, 0, 10), 'TotalItems' => count($items)]);
    }

    /** GET BestsellerGet/ — простая заглушка: сортировка по рейтингу */
    public function bestsellerItemsGet(array $p): array
    {
        $groupId = $this->resolveGroupId($p);

        if ($groupId === null) {
            $categoryIds = [];
        } else {
            $categoryIds = $this->db->getCategoryIdsRecursive($groupId);
        }

        $items = $this->db->getItems($categoryIds ?: null);

        usort($items, fn($a, $b) =>
            (float)$b['ClientRating'] <=> (float)$a['ClientRating']
        );

        [$pageItems, $total] = $this->paginate($items, $p);

        return Response::ok([
            'Items' => $pageItems,
            'TotalItems' => $total
        ]);
    }

    private function resolveGroupId(array $p): ?int
    {
        if (!empty($p['startItemGroupId'])) {
            return (int)$p['startItemGroupId'];
        }
        if (!empty($p['startGroupName'])) {
            return $this->db->findCategoryIdByWebSectionName($p['startGroupName']);
        }
        return null;
    }

    private function paginate(array $items, array $p): array
    {
        $total = count($items);
        $itemsOnPage = isset($p['itemsOnPage']) ? max(1, (int)$p['itemsOnPage']) : 10;
        $pageNumber = isset($p['pageNumber']) ? max(1, (int)$p['pageNumber']) : 1;
        $offset = ($pageNumber - 1) * $itemsOnPage;
        return [array_slice($items, $offset, $itemsOnPage), $total];
    }
}
