<?php

namespace findologicDI\Bundles;

use Exception;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Components\Api\Exception\NotFoundException;
use Shopware\Bundle\SearchBundle;
use SimpleXMLElement;

class ProductNumberSearch implements \Shopware\Bundle\SearchBundle\ProductNumberSearchInterface {

	CONST BASE_URL = 'http://service.findologic.com/ps/';
	CONST ALIVE_ENDPOINT = 'alivetest.php';


	private $originalService;

	private $httpClient;

	private $shopKey;

	private $shopUrl;

	public function __construct( ProductNumberSearchInterface $service ) {
		$this->originalService = $service;
		$this->httpClient      = new \Zend_Http_Client();
		$this->shopKey         = 'E5F652BCAD2871B7B2101B9DF87D24E0'; //Shopware()->Config()->get( 'ShopKey' ); //
		$this->shopUrl         = 'shop.penny.de/'; //explode( '//', Shopware()->Modules()->Core()->sRewriteLink() )[1]; //
	}

	/**
	 * Creates a product search result for the passed criteria object.
	 * The criteria object contains different core conditions and plugin conditions.
	 * This conditions has to be handled over the different condition handlers.
	 *
	 * The search gateway has to implement an event which plugin can be listened to,
	 * to add their own handler classes.
	 *
	 * @param \Shopware\Bundle\SearchBundle\Criteria $criteria
	 * @param \Shopware\Bundle\StoreFrontBundle\Struct $context
	 *
	 * @return \Shopware\Bundle\SearchBundle\ProductNumberSearchResult
	 */
	public function search( Criteria $criteria, \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context ) {

		$requestUrl = $this->buildQueryUrl( $criteria );

		try {
			/* SEND REQUEST TO FINDOLOGIC */
			$request  = $this->httpClient->setUri( $requestUrl );
			$response = $request->request();
			if ( $response->getStatus() == 200 ) {
				/* TLOAD XML RESPONSE */
				$responseText = (string) $response->getBody();
				$xmlResponse  = new SimpleXMLElement( $responseText );

				/* READ PRODUCT IDS */
				$foundProducts = array();
				for ( $i = 0; $i < $xmlResponse->results->count; $i ++ ) {
					try {
						$articleId = (string) $xmlResponse->products->product[ $i ]->attributes();
						/** @var array $baseArticle */
						$baseArticle = Shopware()->Modules()->Articles()->sGetArticleById( $articleId );
						if ( $baseArticle != null and count( $baseArticle ) > 0 ) {
							array_push( $foundProducts, $baseArticle );
						}
					} catch ( Exception $ex ) {
						// No Mapping for Search Results
					}
				}

				/* FACETSS */
				$facets = array();
				foreach ( $xmlResponse->filters->filter as $filter ) {
					$facetItem            = array();
					$facetItem['name']    = (string) $filter->name;
					$facetItem['select']  = (string) $filter->select;
					$facetItem['display'] = (string) $filter->display;
					$facetItem['type']    = (string) $filter->type;
					$facetItem['items']   = $this->createFilterItems( $filter->items->item );

					switch ( $facetItem['type'] ) {
						case "select":
							$facetResult = new TreeFacetResult( $facetItem['name'],
								$facetItem['display'], false, $facetItem['display'], $this->prepareTreeView( $facetItem['items'] ) );
							array_push( $facets, $facetResult );
							break;
						/*case "label":
							$facetResult = new TreeFacetResult( $facetItem['name'],
								$facetItem['display'], false, $facetItem['display'], $this->prepareTreeView( $facetItem['items'] ) );
							array_push( $facets, $facetResult );
							break;*/
						case "range-slider":
							$minValue    = (float) $filter->attributes->selectedRange->min;
							$maxValue    = (float) $filter->attributes->selectedRange->max;
							$facetResult = new RangeFacetResult( $facetItem['name'], $facetItem['display'], $facetItem['display'], $minValue, $maxValue, $minValue, $maxValue, 'von', 'bis' );
							array_push( $facets, $facetResult );
							break;
						default:
							break;
					}


				}

				/* PREPARE SHOPWARE ARRAY */
				$searchResult = array();
				foreach ( $foundProducts as $sProduct ) {
					$searchResult[ $sProduct['ordernumber'] ] = new BaseProduct( $sProduct['articleID'], $sProduct['articleDetailsID'], $sProduct['ordernumber'] );
				}

				return new \Shopware\Bundle\SearchBundle\ProductNumberSearchResult( $searchResult, count( $searchResult ), $facets );
			} else {
				return $this->originalService->search( $criteria, $context );
			}


		} catch ( \Zend_Http_Client_Exception $e ) {
			return $this->originalService->search( $criteria, $context );
		}
	}

	/**
	 * @param \Shopware\Bundle\SearchBundle\Criteria $criteria
	 *
	 * @return mixed
	 */
	private function buildQueryUrl( Criteria $criteria ) {
		/** @var \Shopware\Bundle\SearchBundle\Condition\SearchTermCondition $searchQuery */
		$searchQuery = $criteria->getBaseCondition( 'search' );
		$parameters  = array(
			'shopkey' => $this->shopKey,
			'query'   => $searchQuery->getTerm()
		);
		$url         = self::BASE_URL . $this->shopUrl . 'index.php?' . http_build_query( $parameters );

		return $url;
	}

	private function createFilterItems( $item ) {
		$response = array();
		$tempItem = array();
		foreach ( $item as $subItem ) {
			$tempItem['name'] = (string) $subItem->name;
			if ( $subItem->items->item ) {
				$tempItem['items'] = self::createFilterItems( $subItem->items->item );
			}
			array_push( $response, $tempItem );
		}

		return $response;
	}

	private function prepareTreeView( $items ) {
		$response = array();
		foreach ( $items as $item ) {
			$treeView = new SearchBundle\FacetResult\TreeItem( $item['name'], $item['name'], false, $this->prepareTreeView( $item['items'] ) );
			array_push( $response, $treeView );
		}

		return $response;
	}

}