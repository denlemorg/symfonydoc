<?php

namespace PostingBundle\Service\PostingTransaction\AbsentTransactionDataFinder;

use PostingBundle\Repository\PostingCommodityRepository;
use VtisBundle\Config\Posting\PostingTransactionState;

class AbsentTransactionDataAssigner
{
    private PostingCommodityRepository $postingCommodityRepository;

    public function __construct (
        PostingCommodityRepository $postingCommodityRepository
    ) {
        $this->postingCommodityRepository = $postingCommodityRepository;
    }

    /**
     * @param array<string, array<string, int>> $absentNomenclaturesFromWmrActOfDiscrepancy = [
     *      'nomenclatureGuid1' => [
     *         'postingCommodityOrderGuid1' => (int)productQuantity1,
     *         'postingCommodityOrderGuid2' => (int)productQuantity2,
     *      ],
     *      ...
     * ]
     *
     * @return array<string, int> = [
     *      'postingCommodityGuid1' => (int)postingCommodityQuantity1,
     *      'postingCommodityGuid2' => (int)postingCommodityQuantity2,
     *      ...
     * ]
     */
    public function assign(array $absentNomenclaturesFromWmrActOfDiscrepancy, string $postingGuid): array
    {
        $postingCommoditiesGuidsByWmrNomenclatures = [];
        if (!empty($absentNomenclaturesFromWmrActOfDiscrepancy)) {
            $postingCommoditiesGuidsByWmrNomenclatures =
                $this->getPostingCommoditiesGuidsByWmrNomenclatures(
                    $postingGuid,
                    $absentNomenclaturesFromWmrActOfDiscrepancy
                );
        }

        if (!empty($postingCommoditiesGuidsByWmrNomenclatures)) {
            return $this->getRequiredAbsentTransactionsByPostingCommodityGuids(
                $absentNomenclaturesFromWmrActOfDiscrepancy,
                $postingCommoditiesGuidsByWmrNomenclatures
            );
        }

        return [];
    }

    /**
     * Метод вычисления требуемых транзакций для постинга на основе имеющихся данных от ВМС и имеющихся транзакций в постинге
     *
     * @param array<string, array<string, int>> $absentNomenclaturesFromWmrActOfDiscrepancy = [
     *      'nomenclatureGuid1' => [
     *         'postingCommodityOrderGuid1' => (int)productQuantity1,
     *         'postingCommodityOrderGuid2' => (int)productQuantity2,
     *      ],
     *      ...
     * ]
     *
     * @return array<string, int> = [
     *      'postingCommodityGuid1' => (int)postingCommodityQuantity1,
     *      'postingCommodityGuid2' => (int)postingCommodityQuantity2,
     *      ...
     * ]
     */
    private function getRequiredAbsentTransactionsByPostingCommodityGuids(
        array $absentNomenclaturesFromWmrActOfDiscrepancy,
        array $postingCommoditiesGuidsByWmrNomenclatures
    ): array {
        $existsAbsentTransactionsByNomenclatureGuids = [];
        foreach ($postingCommoditiesGuidsByWmrNomenclatures as $postingCommodity) {
            $nomenclatureGuid = $postingCommodity['nomenclatureGuid'];
            $orderGuid = $postingCommodity['orderGuid'];
            if ($postingCommodity['transactionType'] == PostingTransactionState::ABSENT) {
                if (!isset($existsAbsentTransactionsByNomenclatureGuids[$nomenclatureGuid])) {
                    $existsAbsentTransactionsByNomenclatureGuids[$nomenclatureGuid] = [];
                }
                if (!isset(
                        $existsAbsentTransactionsByNomenclatureGuids[$nomenclatureGuid][$orderGuid]
                    )
                ) {
                    $existsAbsentTransactionsByNomenclatureGuids[$nomenclatureGuid][$orderGuid] = 0;
                }
                $existsAbsentTransactionsByNomenclatureGuids[$nomenclatureGuid][$orderGuid]
                    += $postingCommodity['totalTransactionsQuantity'];
            }
        }

        $requiredAbsentTransactionsByNomenclatureGuid = [];
        foreach ($absentNomenclaturesFromWmrActOfDiscrepancy as $nomenclatureGuid => $quantityByOrderGuids) {
            foreach ($quantityByOrderGuids as $orderGuid => $nomenclatureQuantity) {
                $differenceTransactions = (isset($existsAbsentTransactionsByNomenclatureGuids[$nomenclatureGuid][$orderGuid]))
                    ? ($nomenclatureQuantity - $existsAbsentTransactionsByNomenclatureGuids[$nomenclatureGuid][$orderGuid])
                    : $nomenclatureQuantity;
                if ($differenceTransactions < 0) {
                    $differenceTransactions = 0;
                }

                if ($differenceTransactions > 0) {
                    if (!isset($requiredAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid])) {
                        $requiredAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid] = [];
                    }
                    $requiredAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid][$orderGuid] = $differenceTransactions;
                }
            }
        }

        $requiredAbsentTransactionsByPostingCommodityGuid = [];
        $stackAbsentTransactionsByNomenclatureGuid = $requiredAbsentTransactionsByNomenclatureGuid;
        if (!empty($stackAbsentTransactionsByNomenclatureGuid)) {
            foreach ($postingCommoditiesGuidsByWmrNomenclatures as $postingCommodity) {
                $nomenclatureGuid = $postingCommodity['nomenclatureGuid'];
                $orderGuid = $postingCommodity['orderGuid'];
                $postingCommodityGuid = $postingCommodity['postingCommodityGuid'];
                if (!empty($stackAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid][$orderGuid])) {
                    $remainCommodityTransactions = $postingCommodity['commoditiesQuantity']
                        - $postingCommodity['totalTransactionsQuantity'];
                    if ($stackAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid][$orderGuid] > $remainCommodityTransactions) {
                        $requiredAbsentTransactionsByPostingCommodityGuid[$postingCommodityGuid] = $remainCommodityTransactions;
                        $stackAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid][$orderGuid] -= $remainCommodityTransactions;
                    } else {
                        $requiredAbsentTransactionsByPostingCommodityGuid[$postingCommodityGuid]
                            = $stackAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid][$orderGuid];
                        unset($stackAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid][$orderGuid]);
                    }
                    if (empty($stackAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid])) {
                        unset($stackAbsentTransactionsByNomenclatureGuid[$nomenclatureGuid]);
                    }
                }
            }
        }

        return $requiredAbsentTransactionsByPostingCommodityGuid;
    }

    /**
     * Метод получения данных для гуидов постинглистов с транзакциями существующими и отсутсвующими
     *
     * @param array<string, array<string, int>> $absentNomenclaturesFromWmrActOfDiscrepancy = [
     *      'nomenclatureGuid1' => [
     *         'postingCommodityOrderGuid1' => (int)productQuantity1,
     *         'postingCommodityOrderGuid2' => (int)productQuantity2,
     *      ],
     *      ...
     * ]
     *
     * @return array<int, array> = [
     *      [
     *          'postingCommodityGuid' => (str)postingCommodityGuid,
     *          'orderGuid'            => (str)orderGuid,
     *          ...
     *      ],
     *      ...
     * ]
     */
    private function getPostingCommoditiesGuidsByWmrNomenclatures(
        string $postingGuid,
        array $absentNomenclaturesFromWmrActOfDiscrepancy
    ): array {
        $postingCommoditiesGuidsByWmrNomenclatures = [];

        $nomenclatureGuids = array_keys($absentNomenclaturesFromWmrActOfDiscrepancy);

        $postingCommoditiesWithTransactionsByNomenclatures =
            $this->postingCommodityRepository
                ->findPostingCommodityAndTransactionDataByNomenclatures($postingGuid, $nomenclatureGuids);

        foreach ($postingCommoditiesWithTransactionsByNomenclatures as $postingCommodity) {
            $totalQuantity = $postingCommodity['debit'] + $postingCommodity['credit'];

            $absentTransactionsQuantity = ($postingCommodity['transaction_type'] == PostingTransactionState::ABSENT)
                                            ? $postingCommodity['credit'] : 0;
            $recieveTransactions = ($postingCommodity['transaction_type'] == PostingTransactionState::RECEIVED)
                                    ? $postingCommodity['debit'] : 0;
            $otherTransactions = (!in_array(
                $postingCommodity['transaction_type'], [
                    PostingTransactionState::RECEIVED,
                    PostingTransactionState::ABSENT,
                ]
            )) ? $totalQuantity : 0;

            $postingCommoditiesGuidsByWmrNomenclatures[] = [
                'postingCommodityGuid'       => $postingCommodity['posting_commodity_guid'],
                'orderGuid'                  => $postingCommodity['order_guid'],
                'nomenclatureGuid'           => $postingCommodity['nomenclature_guid'],
                'commoditiesQuantity'        => $postingCommodity['quantity'],
                'transactionType'            => $postingCommodity['transaction_type'],
                'totalTransactionsQuantity'  => $totalQuantity,
                'absentTransactionsQuantity' => $absentTransactionsQuantity,
                'recieveTransactions'        => $recieveTransactions,
                'otherTransactions'          => $otherTransactions,
            ];
        }

        return $postingCommoditiesGuidsByWmrNomenclatures;
    }
}
