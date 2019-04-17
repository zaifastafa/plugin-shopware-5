<?php

namespace FinSearchUnified\Tests\BusinessLogic;

use Assert\AssertionFailedException;
use FinSearchUnified\BusinessLogic\Export;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestBase;
use FinSearchUnified\XmlInformation;
use Shopware\Components\Api\Manager;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;
use Shopware\Models\Category\Repository;
use UnexpectedValueException;

class ExportTest extends TestBase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => 'ABCD0815'
        ],
    ];

    /**
     * @var Export
     */
    private $exportService;

    /**
     * @var Repository
     */
    private $categoryRepository;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        Utility::sResetArticles();
    }

    protected function setUp()
    {
        parent::setUp();

        /** @var Export $exportService */
        $this->exportService = Shopware()->Container()->get('fin_search_unified.business_logic.export');
        $this->categoryRepository = Shopware()->Models()->getRepository(Category::class);
    }

    protected function tearDown()
    {
        parent::tearDown();
        Utility::sResetArticles();
    }

    /**
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        // Reset category active status which was used in the test cases
        $categoryRepository = Shopware()->Models()->getRepository(Category::class);

        $categoryModels = $categoryRepository->findBy(['id' => [5, 42]]);

        /** @var Category $categoryModel */
        foreach ($categoryModels as $categoryModel) {
            $categoryModel->setActive(1);
            Shopware()->Models()->persist($categoryModel);
        }
        Shopware()->Models()->flush();
    }

    /**
     * Data provider for the export test cases with corresponding assertion message
     *
     * @return array
     */
    public function articleProvider()
    {
        $shopCategoryId = 5;

        return [
            'All active articles' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 2,
                'Two articles were expected'
            ],
            '1 out of 2 articles without name' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, false],
                'main_detail' => [true, true],
                'expected' => 1,
                'Article without name was not expected to be returned'
            ],
            '1 out of 2 article main detail is inactive' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, false],
                'expected' => 1,
                'Article without name was not expected to be returned'
            ],
            '1 active and 1 inactive article' => [
                'Active' => [true, false],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 1,
                'Only one article was expected'
            ],
            'All inactive articles' => [
                'Active' => [false, false],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 0,
                'No articles were expected but %d were returned'
            ],
            'Invalid shopkey' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => null,
                'UnexpectedValueException was expected but was not thrown'
            ],
            'Return only first article' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => 1,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 1,
                '1 out of 2 valid articles were expected to be exported'
            ],
            'Skip first article and return all others' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 1,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 1,
                'All except the first article were expected to be exported'
            ],
            'Skip first article and return the next one' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 1,
                'count' => 1,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 1,
                'Only one article was expected to be exported'
            ],
            'Last stock is false and hideNoInStock is true' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 2,
                'Expected variation to be returned but was not'
            ],
            'Last stock is true and hideNoInStock is true and stock is greater than min purchase' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 2,
                'Expected variation to be returned but was not'
            ],
            'Last stock is true and hideNoInStock is true and stock is less than min purchase' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 3,
                'minpurchase' => 5,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 0,
                'Expected that variation is not returned'
            ],
            'Last stock is true and hideNoInStock is false and stock is less than min purchase' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => false,
                'laststock' => 1,
                'instock' => 3,
                'minpurchase' => 5,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 2,
                'Expected that variation is returned'
            ],
            'Only current shop article are considered' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'category_status' => [$shopCategoryId, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 2,
                'Expected that variation is returned'
            ],
            'Other shop articles are not considered' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'category_status' => [42, true],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 0,
                'Expected that variation are not returned'
            ],
            'Only article with active category is considered' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'category_status' => [$shopCategoryId, false],
                'name' => [true, true],
                'main_detail' => [true, true],
                'expected' => 0,
                'Expected that variation is not returned'
            ]
        ];
    }

    /**
     * Method to create test products for the export
     *
     * @param int $number
     * @param bool $hasName
     * @param bool $mainDetailActive
     * @param bool $isActive
     * @param int $laststock
     * @param int $instock
     * @param int $minpurchase
     * @param int $categoryId
     *
     * @return Article|null
     */
    private function createTestProduct(
        $number,
        $hasName,
        $mainDetailActive,
        $isActive,
        $laststock,
        $instock,
        $minpurchase,
        $categoryId
    ) {
        $testArticle = [
            'name' => $hasName ? 'FindologicArticle' . $number : '',
            'active' => $isActive,
            'tax' => 19,
            'lastStock' => $laststock,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => $categoryId]
            ],
            'mainDetail' => [
                'number' => 'FINDOLOGIC' . $number,
                'active' => $mainDetailActive,
                'inStock' => $instock,
                'lastStock' => $laststock,
                'minPurchase' => $minpurchase,
                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'price' => 99.34,
                    ],
                ]
            ]
        ];

        try {
            $manager = new Manager();
            $resource = $manager->getResource('Article');
            $article = $resource->create($testArticle);

            return $article;
        } catch (\Exception $e) {
            echo sprintf("Exception: %s", $e->getMessage());
        }

        return null;
    }

    /**
     * Method to run the export test cases using the data provider
     *
     * @dataProvider articleProvider
     *
     * @param array $isActive
     * @param string $shopkey
     * @param int $start
     * @param int $count
     * @param bool $hideNoInStock
     * @param int $laststock
     * @param int $instock
     * @param int $minpurchase
     * @param array $category
     * @param array $hasName
     * @param array $mainDetailActive
     * @param int|null $expected
     * @param string $errorMessage
     *
     * @throws \Exception
     * @throws AssertionFailedException
     */
    public function testArticleExport(
        array $isActive,
        $shopkey,
        $start,
        $count,
        $hideNoInStock,
        $laststock,
        $instock,
        $minpurchase,
        array $category,
        array $hasName,
        array $mainDetailActive,
        $expected,
        $errorMessage
    ) {
        /** @var Category $categoryModel */
        $categoryModel = $this->categoryRepository->find($category[0]);
        $this->assertInstanceOf(
            Category::class,
            $categoryModel,
            sprintf('Could not find category for given ID: %d', $category[0])
        );

        $categoryModel->setActive($category[1]);
        Shopware()->Models()->persist($categoryModel);
        Shopware()->Models()->flush();

        // Create articles with the provided data to test the export functionality
        for ($i = 0; $i < count($isActive); $i++) {
            $this->createTestProduct(
                $i,
                $hasName[$i],
                $mainDetailActive[$i],
                $isActive[$i],
                $laststock,
                $instock,
                $minpurchase,
                $categoryModel->getId()
            );
        }

        $this->setConfig('hideNoInStock', $hideNoInStock);

        if ($expected === null) {
            $this->expectException(UnexpectedValueException::class);
        }

        /** @var XmlInformation $result */
        $result = $this->exportService->getXml($shopkey, $start, $count);

        if ($expected !== null) {
            $this->assertSame($expected, $result->count, $errorMessage);
        }
    }
}