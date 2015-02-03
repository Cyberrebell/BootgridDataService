<?php

namespace BootgridDataService;

use Doctrine\Common\Persistence\ObjectManager;
use DoctrineEntityReader\EntityReader;
use Zend\Stdlib\Parameters;

class BootgridDataService
{
	const MAX_RESULTS = 1000;
	
	protected $objectManager;
	protected $entityNamespace;
	protected $properties;
	protected $searchableProperties;
	
	function __construct(ObjectManager $om, $entityNamespace) {
		$this->objectManager = $om;
		$this->entityNamespace = $entityNamespace;
	}
	
	function setSearchableProperties(array $properties) {
		$this->searchableProperties = $properties;
	}
	
	function query($current, $rowCount, $searchPhrase, $sortProperty, $order) {
		$queryBuilder = $this->objectManager->createQueryBuilder($this->entityNamespace);
		$queryBuilder->select('u')->from($this->entityNamespace, 'u')->setFirstResult(($current - 1) * $rowCount)->setMaxResults($rowCount);
		if ($sortProperty) {
			$queryBuilder->orderBy('u.' . $sortProperty, strtoupper($order));
		}
		if (!empty($searchPhrase) && !empty($this->searchableProperties)) {
			foreach ($this->searchableProperties as $searchableProperty) {
				$queryBuilder->orWhere(
					$queryBuilder->expr()->like('u.' . $searchableProperty, '?1')
				);
			}
			$queryBuilder->setParameter(1, '%' . $searchPhrase . '%');
		}
		$query = $queryBuilder->getQuery();
		$results = $query->getResult();
		
		$rows = [];
		foreach ($results as $result) {
			$row = [];
			
			foreach ($this->getProperties() as $property) {
				$propertyName = $property->getName();
				$getter = 'get' . ucfirst($propertyName);
				$value = $result->$getter();
				if ($value instanceof \DateTime) {
					$value = $value->format('d.M.Y H:m:s');
				}
				$row[$propertyName] = $value;
			}
			
			$rows[] = $row;
		}
		
		return [
			'current' => $current,
			'rowCount' => $rowCount,
			'rows' => $rows,
			'total' => $this->getTotalCount()
		];
	}
	
	function queryByPost(Parameters $post) {
		$current = $post->get('current', '1');
		$rowCount = $post->get('rowCount', '10');
		if ($rowCount < 0 || $rowCount > self::MAX_RESULTS) {
			$rowCount = self::MAX_RESULTS;
		}
		$searchPhrase = $post->get('searchPhrase', '');
		
		$sortProperty = false;
		$order = false;
		$sort = $post->get('sort', false);
		if ($sort) {
			$key = reset(array_keys($sort));
			if (!empty($key)) {
				$value = $sort[$key];
				if (!empty($value)) {
					$sortProperty = $key;
					$order = $value;
				}
			}
		}
		
		return $this->query($current, $rowCount, $searchPhrase, $sortProperty, $order);
	}
	
	protected function getProperties() {
		if (empty($this->properties)) {
			$this->properties = EntityReader::getProperties($this->entityNamespace);
		}
		return $this->properties;
	}
	
	protected function getTotalCount() {
		$queryBuilder = $this->objectManager->createQueryBuilder($this->entityNamespace);
		$queryBuilder->select('COUNT(u)')->from($this->entityNamespace, 'u');
		return $queryBuilder->getQuery()->getSingleScalarResult();
	}
}
