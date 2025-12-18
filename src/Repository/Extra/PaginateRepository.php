<?php

namespace App\Repository\Extra;

use App\Utils\TypeUtil;
use App\Utils\FonctionUtil;
use App\Utils\PaginateHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

class PaginateRepository extends ServiceEntityRepository
{
    private $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Fonction global de pagination
     * @param string $entity
     * @param int $page // Page courante, par defaut page = 1
     * @param int $limit // Nombre de resultats a renvoyer
     * @param string $order // Ordre de tri
     * @param string $alias
     * @return array
     */
    public function paginator(string $entity, int $page, int $limit, string $order = 'desc', string $alias = 'p'): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select($alias)
            ->from($entity, $alias)
            ->orderBy($alias . '.createdAt', $order)
            ->getQuery();
        return PaginateHelper::paginate($qb, $page, $limit);
    }

    /**
     * Fonction global des requêtes affinées et pagination
     * @param string $entity
     * @param int $page // Page courante, par defaut page = 1
     * @param int $limit // Nombre de resultats a renvoyer
     * @param string $order // Ordre de tri
     * @param string $query // Value de l'element recherché
     * @param array $properties // Les proprietes sur lesquels effectuer la recherche
     * @param string $alias
     * @return array
     */
    public function query(string $entity, int $page, int $limit, string $order = 'desc', ?string $query = null, ?array $properties = null, ?string $alias = 'q'): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select($alias)
            ->from($entity, $alias);

        if ($query && $properties) {
            $orQuery = '(';
            foreach ($properties as $key => $property) {
                if ($key !== 0) {
                    $orQuery .= ' OR ';
                }
                $orQuery .= 'LOWER(' . $alias . '.' . $property . ') LIKE :property_' . $property . '_' . $key;
                $qb->setParameter('property_' . $property . '_' . $key, '%' . strtolower($query) . '%');
            }
            $orQuery .= ')';
            $qb->where($orQuery);
        }

        $qb->orderBy($alias . '.createdAt', $order);

        return PaginateHelper::paginate($qb, $page, $limit);
    }

    /**
     * Fonction pour paginer des données déjà filtrées
     * @param mixed $data // Données déjà filtrées
     * @param int $page // Page courante, par défaut page = 1
     * @param int $limit // Nombre de résultats à renvoyer
     * @param string $order // Ordre de tri
     * @return array
     */
    public function queryData($data, int $page, int $limit, string $order = 'desc'): array
    {
        // Vérifier si $data est un tableau ou peut être converti en tableau
        if (!is_array($data) && method_exists($data, 'toArray')) {
            $data = $data->toArray();
        } else if (!is_array($data) && is_object($data)) {
            try {
                $data = (array) $data;
            } catch (\Exception $e) {
                // Si conversion impossible, utiliser un tableau vide
                $data = [];
            }
        }

        // Calculer l'offset pour la pagination
        $totalItems = count($data);
        $offset = ($page - 1) * $limit;
        $pageItems = array_slice($data, $offset, $limit);

        // Créer un objet de pagination personnalisé (compatible avec PaginateHelper)
        return [
            'member' => $pageItems,
            'currentPage' => $page,
            'totalPages' => (int) ceil($totalItems / $limit),
            'totalItems' => $totalItems,
            'itemsPerPage' => $limit
        ];
    }
}