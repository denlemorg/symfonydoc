<?php

namespace PostingBundle\Service\PostingTransaction\AbsentTransactionDataFinder;

use PostingBundle\Exception\Transaction\AbsentTransactionDataFinderException;
use PostingBundle\Repository\PostingRepository;
use VtisBundle\Config\Posting\PostingState;

class AbsentTransactionDataFinder
{
    private NomenclatureFinderFactory $nomenclatureFinderFactory;
    private PostingRepository $postingRepository;
    private AbsentTransactionDataAssigner $absentTransactionDataAssigner;

    public function __construct(
        NomenclatureFinderFactory     $nomenclatureFinderFactory,
        PostingRepository             $postingRepository,
        AbsentTransactionDataAssigner $absentTransactionDataAssigner
    ) {
        $this->nomenclatureFinderFactory = $nomenclatureFinderFactory;
        $this->postingRepository = $postingRepository;
        $this->absentTransactionDataAssigner = $absentTransactionDataAssigner;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return null|array<string, int> = [
     *      'postingCommodityGuid1' => (int)postingCommodityQuantity1,
     *      'postingCommodityGuid2' => (int)postingCommodityQuantity2,
     *      ...
     * ]
     * @throws AbsentTransactionDataFinderException
     */
    public function find(
        string $handleType,
        string $postingGuid,
        array $data = []
    ): ?array {
        $this->validate($postingGuid);

        $finder = $this->nomenclatureFinderFactory->getFinder($handleType);

        $absentNomenclatures = $finder->find($postingGuid, $data);

        if (!empty($absentNomenclatures)) {
            $absentTransactionsData = $this->absentTransactionDataAssigner
                ->assign($absentNomenclatures, $postingGuid);
        }

        return !empty($absentTransactionsData) ? $absentTransactionsData : null;
    }

    /**
     * @throws AbsentTransactionDataFinderException
     */
    public function validate(string $postingGuid): void
    {
        $posting = $this->postingRepository->getByGuid($postingGuid);
        if ($posting->getState() >= PostingState::APPROVED) {
            throw new AbsentTransactionDataFinderException('Перемещение уже проведено или удалено');
        }
    }
}
