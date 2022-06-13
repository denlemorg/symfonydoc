<?php

namespace PostingBundle\Service\PostingTransaction\AbsentTransactionDataFinder\Finders;

use PostingBundle\Exception\Transaction\AbsentTransactionDataFinderException;

class DraftActNomenclatureFinder implements NomenclatureFinderInterface
{
    /**
     * @param array<string, mixed> $data = [
     *      'deficitsProductsFromWMR' => [
     *          [
     *              'product_id' => (int)wmrProductId,
     *              'quantity'   => (float)quantity,
     *              ...
     *          ],
     *          ...
     *      ]
     *      'postingCommodityOrders' => [
     *          (int)localWmrShipmentOrderId1 => (string)vtisPostingCommodityOrderGuid1,
     *          (int)localWmrShipmentOrderId2 => (string)vtisPostingCommodityOrderGuid2,
     *          ...
     *      ],
     *      'nomenclatures' => [
     *          (int)localWmrNomenclatureId1 => (string)vtisNomenclatureGuid1,
     *          (int)localWmrNomenclatureId2 => (string)vtisNomenclatureGuid2,
     *          ...
     *      ],
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
        if (empty($data['deficitsProductsFromWMR'])) {
            throw new AbsentTransactionDataFinderException('Отсутствует или пустой параметр deficitsProductsFromWMR');
        }
        if (empty($data['nomenclatures'])) {
            throw new AbsentTransactionDataFinderException('Отсутствует или пустой параметр nomenclatures');
        }

        if (empty($data['postingCommodityOrders'])) {
            throw new AbsentTransactionDataFinderException('Отсутствует или пустой параметр postingCommodityOrders');
        }

        $absentNomenclaturesFromWmrActOfDiscrepancy = [];
        $postingCommodityOrders = $data['postingCommodityOrders'];
        $nomenclatures = $data['nomenclatures'];
        foreach ($data['deficitsProductsFromWMR'] as $productData) {
            $count = (int)($productData['quantity'] ?? 0);
            $factor = (int)($productData['factor'] ?? 0);
            $count *= $factor;
            $nomenclatureGuid = $nomenclatures[$productData['product_id']] ?? '';
            $postingCommodityOrderGuid = $postingCommodityOrders[$productData['shipment_order_id']] ?? '';
            if (!$nomenclatureGuid || !$postingCommodityOrderGuid) {
                continue;
            }
            if (!isset($absentNomenclaturesFromWmrActOfDiscrepancy[$nomenclatureGuid])) {
                $absentNomenclaturesFromWmrActOfDiscrepancy[$nomenclatureGuid] = [];
            }
            if (!isset($absentNomenclaturesFromWmrActOfDiscrepancy[$nomenclatureGuid][$postingCommodityOrderGuid])) {
                $absentNomenclaturesFromWmrActOfDiscrepancy[$nomenclatureGuid][$postingCommodityOrderGuid] = 0;
            }
            $absentNomenclaturesFromWmrActOfDiscrepancy[$nomenclatureGuid][$postingCommodityOrderGuid] += $count;
        }

        return $absentNomenclaturesFromWmrActOfDiscrepancy;
    }
}
