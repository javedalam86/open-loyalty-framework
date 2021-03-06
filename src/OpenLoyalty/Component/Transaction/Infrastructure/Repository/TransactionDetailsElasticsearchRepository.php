<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Component\Transaction\Infrastructure\Repository;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use OpenLoyalty\Component\Core\Infrastructure\Repository\OloyElasticsearchRepository;
use OpenLoyalty\Component\Transaction\Domain\ReadModel\TransactionDetailsRepository;

/**
 * Class TransactionDetailsElasticsearchRepository.
 */
class TransactionDetailsElasticsearchRepository extends OloyElasticsearchRepository implements TransactionDetailsRepository
{
    protected $dynamicFields = [
        [
            'grossValue' => [
                'match' => 'grossValue',
                'mapping' => [
                    'type' => 'double',
                ],
            ],
        ],
        [
            'maker' => [
                'match' => 'maker',
                'match_mapping_type' => 'string',
                'mapping' => [
                    'type' => 'string',
                    'analyzer' => 'small_letters',
                ],
            ],
        ],
        [
            'category' => [
                'match' => 'category',
                'match_mapping_type' => 'string',
                'mapping' => [
                    'type' => 'string',
                    'analyzer' => 'small_letters',
                ],
            ],
        ],
        [
            'label_value' => [
                'path_match' => 'items.labels.*',
                'mapping' => [
                    'type' => 'string',
                    'analyzer' => 'small_letters',
                ],
            ],
        ],
        [
            'transaction_label_value' => [
                'path_match' => 'labels.*',
                'mapping' => [
                    'type' => 'string',
                    'analyzer' => 'small_letters',
                ],
            ],
        ],
        [
            'document_number_raw' => [
                'match' => 'documentNumberRaw',
                'match_mapping_type' => 'string',
                'mapping' => [
                    'type' => 'string',
                    'index' => 'not_analyzed',
                ],
            ],
        ],
    ];

    public function findInPeriod(\DateTime $from, \DateTime $to, $onlyWithCustomers = true)
    {
        $filter = [];
        $filter[] = ['range' => [
            'purchaseDate' => [
                'gte' => $from->getTimestamp(),
                'lte' => $to->getTimestamp(),
            ],
        ]];
        $query = array(
            'bool' => array(
                'must' => [[
                    'bool' => [
                        'should' => $filter,
                    ],
                ]],
            ),
        );

        if ($onlyWithCustomers) {
            $query['bool']['must'][]['exists'] = ['field' => 'customerId'];
        }

        return $this->query($query);
    }

    public function findAllWithCustomer()
    {
        $query = array(
            'bool' => array(
                'must' => array(
                    'exists' => ['field' => 'customerId'],
                ),
            ),
        );

        return $this->query($query);
    }

    public function findBySKUs(array $skuIds, $withCustomer = true)
    {
        if (count($skuIds) == 0) {
            return [];
        }
        $filter = [];
        foreach ($skuIds as $id) {
            $filter[] = ['term' => [
                'items.sku.code' => strtolower($id),
            ]];
        }

        $query = array(
            'bool' => array(
                'must' => [[
                    'bool' => [
                        'should' => $filter,
                    ],
                ]],
            ),
        );

        if ($withCustomer) {
            $query['bool']['must'][]['exists'] = ['field' => 'customerId'];
        }

        return $this->query($query);
    }

    public function findByMakers(array $makers, $withCustomer = true)
    {
        if (count($makers) == 0) {
            return [];
        }
        $filter = [];
        foreach ($makers as $maker) {
            $filter[] = ['term' => [
                'items.maker' => strtolower($maker),
            ]];
        }

        $query = array(
            'bool' => array(
                'must' => [[
                    'bool' => [
                        'should' => $filter,
                    ],
                ]],
            ),
        );

        if ($withCustomer) {
            $query['bool']['must'][]['exists'] = ['field' => 'customerId'];
        }

        return $this->query($query);
    }

    public function findByLabels(array $labels, $withCustomer = true)
    {
        if (count($labels) == 0) {
            return [];
        }
        $filter = [];
        foreach ($labels as $label) {
            $filter[] = ['bool' => ['must' => [
                    ['term' => [
                            'items.labels.key' => strtolower($label['key']),
                        ],
                    ],
                    ['term' => [
                            'items.labels.value' => strtolower($label['value']),
                        ],
                    ],
                ],
            ]];
        }

        $query = array(
            'bool' => array(
                'must' => [[
                    'bool' => [
                        'should' => $filter,
                    ],
                ]],
            ),
        );

        if ($withCustomer) {
            $query['bool']['must'][]['exists'] = ['field' => 'customerId'];
        }

        return $this->query($query);
    }

    /**
     * @param array  $params
     * @param bool   $exact
     * @param int    $page
     * @param int    $perPage
     * @param null   $sortField
     * @param string $direction
     *
     * @return array
     */
    public function findByParametersPaginated(
        array $params,
        $exact = true,
        $page = 1,
        $perPage = 10,
        $sortField = null,
        $direction = 'DESC'
    ) {
        $params = $this->prepareLabels($params);

        return parent::findByParametersPaginated($params, $exact, $page, $perPage, $sortField, $direction);
    }

    public function countTotal(array $params = [], $exact = true)
    {
        $params = $this->prepareLabels($params);

        return parent::countTotal($params, $exact);
    }

    public function getAvailableLabels()
    {
        $query = array(
            'index' => $this->index,
            'body' => array(
                'aggregations' => [
                    'labels_key' => [
                        'terms' => ['field' => 'items.labels.key'],
                        'aggregations' => [
                            'label_values' => [
                                'terms' => [
                                    'field' => 'items.labels.value',
                                ],
                            ],
                        ],
                    ],
                ],
            ),
            'size' => 0,
        );

        try {
            $result = $this->client->search($query);
        } catch (Missing404Exception $e) {
            return [];
        }

        if (!array_key_exists('aggregations', $result)) {
            return [];
        }

        if (!array_key_exists('labels_key', $result['aggregations'])) {
            return [];
        }
        $labels = [];
        $labelKeys = $result['aggregations']['labels_key'];

        foreach ($labelKeys['buckets'] as $bucket) {
            $labels[$bucket['key']] = $this->getLabelValuesForBucket($bucket['label_values']);
        }

        return $labels;
    }

    protected function getLabelValuesForBucket(array $values)
    {
        $val = [];
        foreach ($values['buckets'] as $bucket) {
            $val[] = $bucket['key'];
        }

        return $val;
    }

    /**
     * @param array $params
     *
     * @return array
     */
    private function prepareLabels(array $params): array
    {
        if (isset($params['labels'])) {
            $labelsFilter = $params['labels'];
            unset($params['labels']);

            foreach ($labelsFilter as $label) {
                $fields = [];
                if (empty($label['key']) && empty($label['value'])) {
                    continue;
                }
                if (!empty($label['key'])) {
                    $fields['labels.key'] = $label['key'];
                }
                if (!empty($label['value'])) {
                    $fields['labels.value'] = $label['value'];
                }
                $params[] = [
                    'type' => 'multiple_all',
                    'exact' => true,
                    'fields' => $fields,
                ];
            }
        }

        return $params;
    }
}
