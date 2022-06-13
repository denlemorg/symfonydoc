<?php

namespace PostingBundle\Service\PostingTransaction\AbsentTransactionDataFinder;

use PostingBundle\Exception\Transaction\AbsentTransactionDataFinderException;
use PostingBundle\Service\PostingTransaction\AbsentTransactionDataFinder\Finders\ApprovedActNomenclatureFinder;
use PostingBundle\Service\PostingTransaction\AbsentTransactionDataFinder\Finders\DraftActNomenclatureFinder;
use PostingBundle\Service\PostingTransaction\AbsentTransactionDataFinder\Finders\NomenclatureFinderInterface;

class NomenclatureFinderFactory
{
    public const INTERNAL_PURCHASE_DISCREPANCY_GET_HANDLER = 'internalPurchaseDiscrepancyGetHandler';
    public const INTERNAL_PURCHASE_DISCREPANCY_APPROVE_HANDLER = 'internalPurchaseDiscrepancyApproveHandler';

    private DraftActNomenclatureFinder $draftActNomenclatureFinder;
    private ApprovedActNomenclatureFinder $approvedActNomenclatureFinder;

    public function __construct(
        DraftActNomenclatureFinder    $draftActNomenclatureFinder,
        ApprovedActNomenclatureFinder $approvedActNomenclatureFinder
    ) {
        $this->draftActNomenclatureFinder = $draftActNomenclatureFinder;
        $this->approvedActNomenclatureFinder = $approvedActNomenclatureFinder;
    }

    /**
     * @throws AbsentTransactionDataFinderException
     */
    public function getFinder(string $handleType): NomenclatureFinderInterface
    {
        switch ($handleType) {
            case self::INTERNAL_PURCHASE_DISCREPANCY_GET_HANDLER:
                return $this->draftActNomenclatureFinder;
            case self::INTERNAL_PURCHASE_DISCREPANCY_APPROVE_HANDLER:
                return $this->approvedActNomenclatureFinder;
            default:
                throw new AbsentTransactionDataFinderException('Не найден finder');
        }
    }
}
