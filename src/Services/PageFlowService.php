<?php
declare(strict_types=1);

final class PageFlowService
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /** GET PageFlowService/BreadCrumbsInfoGet/?detailId= */
    public function breadCrumbsInfoGet(array $p): array
    {
        $detailId = isset($p['detailId']) ? (int)$p['detailId'] : 0;
        
        $item = $this->db->getItem($detailId); 
        if (!$item) {
            return Response::ok(['CatalogPaths' => []]);
        }

        $categoryId = $item['Detail']['BranchId'] ?? 0;
        $paths = [];
        $levelCounter = 0; // Будем считать категории с конца, а потом перевернем уровни

        while ($categoryId > 0) {
            $category = $this->db->getCategoryById($categoryId); 
            if (!$category) {
                break;
            }

            $levelCounter++;
            
            // Добавляем все поля строго по Java-классу CatalogPath
            array_unshift($paths, [
                'CatalogId'        => (int)$category['Id'],
                'DisplayName'      => (string)$category['Name'],
                'ShortDisplayName' => (string)$category['Name'], // Заглушка, дублируем имя
                'Level'            => $levelCounter,            // Временный уровень
                'Name'             => (string)$category['WebSectionName']
            ]);

            $categoryId = $category['ParentID'] ?? 0;
        }

        // Корректируем уровни, чтобы у корня был Level = 1, у следующей = 2 и так далее
        $totalCats = count($paths);
        foreach ($paths as $index => &$path) {
            $path['Level'] = $index + 1;
        }
        unset($path);

        return Response::ok(['CatalogPaths' => $paths]);
    }

    /** GET PageFlowService/ContextInfoGet/?contextName= */
    public function contextInfoGet(array $p): array
    {
        $contextName = (string)($p['contextName'] ?? '');

        // 1. Получаем из нашей XML-базы категории верхнего уровня (parentId = 0)
        $categories = $this->db->getCategories(0);
        
        $childs = [];
        foreach ($categories as $cat) {
            // Формируем структуру строго по декомпилированному классу CatalogWebSection
            $childs[] = [
                'CatalogId'   => (int)$cat['Id'],                 // Строго CatalogId вместо Id!
                'Name'        => 'div_' . $cat['WebSectionName'], // Внутренний тег (div_tech, div_books)
                'DisplayName' => (string)$cat['Name'],            // Название на русском
                'Url'         => '',                              // Обязательная строковая заглушка
                'Childs'      => []                               // Пустой массив для вложенных подкатегорий
            ];
        }

        return Response::ok([
            'WebSection' => [
                'CatalogId'   => 0,
                'Name'        => $contextName !== '' ? $contextName : 'root',
                'DisplayName' => 'Каталог',
                'Url'         => '',
                'Childs'      => $childs // Массив категорий, у которого Java вызовет .getChilds()
            ]
        ]);
    }

}
