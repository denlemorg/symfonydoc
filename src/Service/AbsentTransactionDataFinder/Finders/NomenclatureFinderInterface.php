<?php

namespace PostingBundle\Service\PostingTransaction\AbsentTransactionDataFinder\Finders;

interface NomenclatureFinderInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @return null|array<string, array<string, int>> = [
     *      'nomenclatureGuid1' => [
     *         'postingCommodityOrderGuid1' => (int)productQuantity1,
     *         'postingCommodityOrderGuid2' => (int)productQuantity2,
     *      ],
     *      ...
     * ]
     */
    public function find(string $postingGuid, array $data = []): ?array;
}
