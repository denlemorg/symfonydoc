<?php

namespace PostingBundle\Service\PostingTransaction\AbsentTransactionDataFinder\Finders;

use PostingBundle\Exception\Transaction\AbsentTransactionDataFinderException;
use WmrBundle\Repository\WmrDocuments\WmrInternalPurchaseDiscrepancyGoodRepository;

class ApprovedActNomenclatureFinder implements NomenclatureFinderInterface
{
    protected WmrInternalPurchaseDiscrepancyGoodRepository $wmrInternalPurchaseDiscrepancyGoodRepository;

    public function __construct(
        WmrInternalPurchaseDiscrepancyGoodRepository $wmrInternalPurchaseDiscrepancyGoodRepository
    ) {
        $this->wmrInternalPurchaseDiscrepancyGoodRepository = $wmrInternalPurchaseDiscrepancyGoodRepository;
    }

    /**
     * @param array<string, mixed> $data = [
     *      'docId' => (int)wmrDocumentId,
     *      ...
     * ]
     *
     * @return null|array<string, array<string, int>> = [
     *      'nomenclatureGuid1' => [
     *         'postingCommodityOrderGuid1' => (int)productQuantity1,
     *         'postingCommodityOrderGuid2' => (int)productQuantity2,
     *      ],
     *      ...
     * ]
     * @throws AbsentTransactionDataFinderException
     */
    public function find(
        string $postingGuid,
        array $data = []
    ): ?array {
        if (empty($data['docId'])) {
            throw new AbsentTransactionDataFinderException('Отсутствует или пустой параметр docId');
        }

        $absentNomenclaturesFromTableParts = [];

        $absentNomenclaturesFromTablePartsNotFormatted =
            $this->wmrInternalPurchaseDiscrepancyGoodRepository
                ->findAbsentNomenclaturesFromTableParts($data['docId'], $postingGuid);

        if (!empty($absentNomenclaturesFromTablePartsNotFormatted)) {
            foreach ($absentNomenclaturesFromTablePartsNotFormatted as $product) {
                $nomenclatureGuid = $product['nomenclatureGuid'];
                $postingCommodityOrderGuid = $product['shipmentOrderGuid'];
                if (!$nomenclatureGuid || !$postingCommodityOrderGuid) {
                    continue;
                }

                if (!isset($absentNomenclaturesFromTableParts[$nomenclatureGuid])) {
                    $absentNomenclaturesFromTableParts[$nomenclatureGuid] = [];
                }

                if (!isset($absentNomenclaturesFromTableParts[$nomenclatureGuid][$postingCommodityOrderGuid])) {
                    $absentNomenclaturesFromTableParts[$nomenclatureGuid][$postingCommodityOrderGuid] = 0;
                }

                $absentNomenclaturesFromTableParts[$nomenclatureGuid][$postingCommodityOrderGuid] += $product['quantity'];
            }
        }

        return $absentNomenclaturesFromTableParts;
    }
}
