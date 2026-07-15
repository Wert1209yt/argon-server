<?php
/**
 * PartnerService/SearchService/*
 * SearchItemsGet, SearchSuggestionsGet
 */
final class SearchService
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /** GET SearchItemsGet/?searchText=&startItemGroupId=&startGroupName=&itemsOnPage=&pageNumber= */
    public function searchItemsGet(array $p): array
    {
        $text = $p['searchText'] ?? '';
        $categoryId = null;
        if (!empty($p['startItemGroupId'])) {
            $categoryId = (int)$p['startItemGroupId'];
        } elseif (!empty($p['startGroupName'])) {
            $categoryId = $this->db->findCategoryIdByWebSectionName($p['startGroupName']);
        }

        $found = $this->db->searchItems($text, $categoryId);

        $itemsOnPage = isset($p['itemsOnPage']) ? max(1, (int)$p['itemsOnPage']) : 10;
        $pageNumber = isset($p['pageNumber']) ? max(1, (int)$p['pageNumber']) : 1;
        $offset = ($pageNumber - 1) * $itemsOnPage;
        $page = array_slice($found, $offset, $itemsOnPage);

        return Response::ok([
            'SearchedItems' => $page,
            'ItemsCount'    => count($found),
            'GroupWeights'  => [],
            'WordForms'     => $text,
        ]);
    }

    /** GET SearchSuggestionsGet/?searchText= */
    public function searchSuggestionsGet(array $p): array
    {
        $text = $p['searchText'] ?? '';
        $suggestions = $this->db->suggestItems($text);

        return Response::ok(['Suggestions' => $suggestions]);
    }
}
