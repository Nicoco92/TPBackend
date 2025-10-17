<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/categories', name: 'api_categorie_')]
class CategorieController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategorieRepository $categorieRepository,
        private LoggerInterface $logger
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $categories = $this->categorieRepository->findAll();
            
            $data = array_map(function(Categorie $categorie) {
                return $this->serializeCategorie($categorie);
            }, $categories);

            return $this->json($data, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des catégories', [
                'error' => $e->getMessage()
            ]);
            return $this->json(
                ['error' => 'Une erreur est survenue'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        try {
            $categorie = $this->categorieRepository->find($id);

            if (!$categorie) {
                return $this->json(
                    ['error' => 'Catégorie non trouvée'],
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->json($this->serializeCategorie($categorie), Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération de la catégorie', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->json(
                ['error' => 'Une erreur est survenue'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json(
                    ['error' => 'JSON invalide'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!isset($data['nom']) || empty($data['nom'])) {
                return $this->json(
                    ['error' => "Le champ 'nom' est requis"],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $categorie = new Categorie();
            $categorie->setNom($data['nom']);

            if (isset($data['description'])) {
                $categorie->setDescription($data['description']);
            }

            $this->em->persist($categorie);
            $this->em->flush();

            $this->logger->info('Catégorie créée avec succès', ['id' => $categorie->getId()]);

            return $this->json(
                $this->serializeCategorie($categorie),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création de la catégorie', [
                'error' => $e->getMessage()
            ]);
            return $this->json(
                ['error' => 'Une erreur est survenue'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function serializeCategorie(Categorie $categorie): array
    {
        return [
            'id' => $categorie->getId(),
            'nom' => $categorie->getNom(),
            'description' => $categorie->getDescription()
        ];
    }
}