<?php
/**
 * @package plugins.elasticSearch
 * @subpackage model.search
 */

abstract class kBaseESearch extends kBaseSearch
{
	const GLOBAL_HIGHLIGHT_CONFIG = 'globalMaxNumberOfFragments';

	public abstract function getPeerName();

	public abstract function getPeerRetrieveFunctionName();

	public abstract function getElasticTypeName();

	protected function execSearch(ESearchOperator $eSearchOperator)
	{
		$subQuery = $eSearchOperator::createSearchQuery($eSearchOperator->getSearchItems(), null, $this->queryAttributes, $eSearchOperator->getOperator());
		$this->handleDisplayInSearch();
		$this->mainBoolQuery->addToMust($subQuery);
		$this->applyElasticSearchConditions();
		$this->addGlobalHighlights();
		$result = $this->elasticClient->search($this->query, true, true);
		$this->addSearchTermsToSearchHistory();
		return $result;
	}

	protected function initQuery(array $statuses, $objectId, kPager $pager = null, ESearchOrderBy $order = null)
	{
		$partnerId = kBaseElasticEntitlement::$partnerId;
		$this->initQueryAttributes($partnerId, $objectId);
		$this->initBaseFilter($partnerId, $statuses, $objectId);
		$this->initPager($pager);
		$this->initOrderBy($order);
	}

	protected function addGlobalHighlights()
	{
		$this->queryAttributes->getQueryHighlightsAttributes()->setScopeToGlobal();
		$numOfFragments = elasticSearchUtils::getNumOfFragmentsByConfigKey(self::GLOBAL_HIGHLIGHT_CONFIG);
		$highlight = new kESearchHighlightQuery($this->queryAttributes->getQueryHighlightsAttributes()->getFieldsToHighlight(), $numOfFragments);
		$highlight = $highlight->getFinalQuery();
		if($highlight)
		{
			$this->query['body']['highlight'] = $highlight;
		}
	}

	protected function addSearchTermsToSearchHistory()
	{
		$searchTerms = $this->queryAttributes->getSearchHistoryTerms();
		$searchTerms = array_unique($searchTerms);
		$searchTerms = array_values($searchTerms);
		if (!$searchTerms)
		{
			KalturaLog::log("Empty search terms, not adding to search history");
			return;
		}

		$searchHistoryInfo = new ESearchSearchHistoryInfo();
		$searchHistoryInfo->setSearchTerms($searchTerms);
		$searchHistoryInfo->setPartnerId(kBaseElasticEntitlement::$partnerId);
		$searchHistoryInfo->setKUserId(kBaseElasticEntitlement::$kuserId);
		$searchHistoryInfo->setSearchContext(searchHistoryUtils::getSearchContext());
		$searchHistoryInfo->setSearchedObject($this->getElasticTypeName());
		$searchHistoryInfo->setTimestamp(time());
		kEventsManager::raiseEventDeferred(new kESearchSearchHistoryInfoEvent($searchHistoryInfo));
	}

}
