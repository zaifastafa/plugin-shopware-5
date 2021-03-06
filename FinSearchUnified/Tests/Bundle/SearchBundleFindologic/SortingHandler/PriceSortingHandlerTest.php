<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\SortingHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\PriceSortingHandler;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Sorting\PriceSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

class PriceSortingHandlerTest extends TestCase
{
    /**
     * @var QueryBuilder
     */
    private $querybuilder;

    /**
     * @var ProductContextInterface
     */
    private $context = null;

    /**
     * @throws Exception
     */
    protected function setUp()
    {
        parent::setUp();

        $this->querybuilder = new SearchQueryBuilder(
            Shopware()->Container()->get('http_client'),
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            Shopware()->Config()
        );
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
    }

    public function orderByDataProvider()
    {
        return [
            'Direction is ASC' => [SortingInterface::SORT_ASC, 'price ASC'],
            'Direction is DESC' => [SortingInterface::SORT_DESC, 'price DESC']
        ];
    }

    /**
     * @dataProvider orderByDataProvider
     *
     * @param string $direction
     * @param string $expectedOrder
     */
    public function testGenerateSorting($direction, $expectedOrder)
    {
        $handler = new PriceSortingHandler();
        $handler->generateSorting(
            new PriceSorting($direction),
            $this->querybuilder,
            $this->context
        );

        $parameters = $this->querybuilder->getParameters();

        $this->assertArrayHasKey('order', $parameters, 'Price Sorting was not applied');
        $this->assertSame($expectedOrder, $parameters['order'], sprintf(
            'Expected sorting to be %s',
            $expectedOrder
        ));
    }
}
