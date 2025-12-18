<?php

namespace App\Utils;

use Doctrine\ORM\Tools\Pagination\Paginator;

class PaginateHelper
{
    /**
     * Pagination des query
     * @param $qb
     * @param integer $page
     * @param integer $limit
     * @return array{member: array, currentPage: int, totalPages: int, totalItems: int, itemsPerPage: int}
     */
    public static function paginate($qb, int $page, int $limit): array
    {
        $paginator = new Paginator($qb);
        
        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $limit);
        
        $paginator
            ->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        return [
            'member' => iterator_to_array($paginator->getIterator()),
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $limit
        ];
    }
}