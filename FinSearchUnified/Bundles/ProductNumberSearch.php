<?php

namespace FinSearchUnified\Bundles;

use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Models\Search\CustomFacet;

class ProductNumberSearch implements ProductNumberSearchInterface
{
    private $urlBuilder;

    private $originalService;

    public function __construct(ProductNumberSearchInterface $service)
    {
        $this->urlBuilder = new UrlBuilder();
        $this->originalService = $service;
    }

    /**
     * Creates a product search result for the passed criteria object.
     * The criteria object contains different core conditions and plugin conditions.
     * This conditions has to be handled over the different condition handlers.
     *
     * The search gateway has to implement an event which plugin can be listened to,
     * to add their own handler classes.
     *
     * @param \Shopware\Bundle\SearchBundle\Criteria                        $criteria
     * @param \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
     *
     * @return SearchBundle\ProductNumberSearchResult
     */
    public function search(Criteria $criteria, ShopContextInterface $context)
    {
        if (
            StaticHelper::checkDirectIntegration() ||
            !(bool) Shopware()->Config()->get('ActivateFindologic') ||
            (
                !(bool) Shopware()->Config()->get('ActivateFindologicForCategoryPages') &&
                !StaticHelper::checkIfSearch($criteria->getConditions())
            )
        ) {
            return $this->originalService->search($criteria, $context);
        }

        try {

            /* SEND REQUEST TO FINDOLOGIC */
            $this->urlBuilder->setCustomerGroup($context->getCurrentCustomerGroup());
            $response = $this->urlBuilder->buildQueryUrlAndGetResponse($criteria);
            if ($response instanceof \Zend_Http_Response && $response->getStatus() == 200) {

                $xmlResponse = StaticHelper::getXmlFromResponse($response);

                $hasLandingpage = StaticHelper::checkIfRedirect($xmlResponse);

                if ($hasLandingpage != null){
                    header('Location: '.$hasLandingpage);
                    exit();
                }

                $foundProducts = StaticHelper::getProductsFromXml($xmlResponse);

                $facets = StaticHelper::getFacetResultsFromXml($xmlResponse);

                $searchResult = StaticHelper::getShopwareArticlesFromFindologicId($foundProducts);
                setcookie('Fallback', 0);
                $totalResults = (int) $xmlResponse->results->count;

                $facetsInterfaces = StaticHelper::getFindologicFacets($xmlResponse);


                /** @var CustomFacet $facets_interface */
                foreach ($facetsInterfaces as $facets_interface){
                    $criteria->addFacet($facets_interface->getFacet());
                }

                $allConditions= $criteria->getConditions();


                foreach ($allConditions as $condition){

                    if ($condition instanceof SearchBundle\Condition\ProductAttributeCondition){
                        $currentFacet = $criteria->getFacet($condition->getName());
                        if ($currentFacet instanceof SearchBundle\FacetInterface){

                            /** @var SearchBundle\Facet\ProductAttributeFacet $currentFacet */
                            $tempFacet = StaticHelper::createSelectedFacet($currentFacet->getFormFieldName(), $currentFacet->getLabel(), $condition->getValue());
                            if (count($tempFacet->getValues()) == 0){
                                continue;
                            }
                            $foundFacet = StaticHelper::arrayHasFacet($facets, $currentFacet->getFormFieldName());

                            if (!$foundFacet){
                                $facets[] = $tempFacet;
                            }
                        }

                    }
                }
                $criteria->resetConditions();
                return new SearchBundle\ProductNumberSearchResult($searchResult, $totalResults, $facets);
            } else {
                setcookie('Fallback', 1);

                return $this->originalService->search($criteria, $context);
            }
        } catch (\Zend_Http_Client_Exception $e) {
            setcookie('Fallback', 1);

            return $this->originalService->search($criteria, $context);
        }
    }
}