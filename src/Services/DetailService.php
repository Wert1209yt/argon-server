<?php
/**
 * PartnerService/DetailService/*
 * DetailGet, DetailRatingGet, DetailCommentsGet (заглушка без отзывов)
 */
final class DetailService
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /** GET DetailGet/?detailId= */
    public function detailGet(array $p): array
    {
        $id = (int)($p['detailId'] ?? $p['id'] ?? 0);
        $item = $this->db->getItem($id);

        if (!$item) {
            return Response::error('Detail_NotFound');
        }

        return Response::ok([
            'DetailId' => $id,
            'Detail' => [
              'Id'             => (int)$item['Id'],
              'BranchId'       => (int)($item['Detail']['BranchId'] ?? 0),
              'DestinationId'  => (int)($item['Detail']['BranchId'] ?? 0),
              'ViewId'         => (int)$item['Id'],
              'ItemType'       => [
                  'Id'    => (int)($item['ItemTypeId'] ?? 1),
                  'Name'  => (string)($item['ItemType'] ?? 'Товар'),
                  'Brief' => (string)($item['ItemType'] ?? 'Товар')
              ],
              'ClassType' => [
                  'Id'    => (int)($item['ItemTypeId'] ?? 1),
                  'Name'  => (string)($item['ItemType'] ?? 'Товар')
              ],
              'ClassAttributes'   => $item['Detail']['ClassAttributes'] ?? [],
              'ModifyMomentValue' => [
                  'DateTime' => date('Y-m-d\TH:i:s')
              ]
            ]
        ]);
    }


    /** GET DetailRatingGet/?detailId= */
    public function detailRatingGet(array $p): array
    {
        $id = (int)($p['detailId'] ?? 0);
        $item = $this->db->getItem($id);
        if ($item === null) {
            return Response::error('Detail_Rating_NotFound');
        }
        return Response::ok(['RatingInfo' => [
            'ClientRating'      => $item['ClientRating'],
            'ClientRatingCount' => $item['ClientRatingCount'],
        ]]);
    }

    /** GET DetailCommentsGet/?detailId=&pageNumber=&itemsOnPage= — отзывов пока нет в БД */
    public function detailCommentsGet(array $p): array
    {
        return Response::ok(['Reviews' => [], 'ReviewsCount' => 0]);
    }
}
