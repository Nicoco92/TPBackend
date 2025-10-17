<?php

namespace App\Controller;

use App\Entity\Livre;
use App\Entity\Auteur;
use App\Entity\Categorie;
use App\Repository\LivreRepository;
use App\Repository\AuteurRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/livres', name: 'api_livre_')]
class LivreController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LivreRepository $livreRepository,
        private AuteurRepository $auteurRepository,
        private CategorieRepository $categorieRepository,
        private LoggerInterface $logger
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $livres = $this->livreRepository->findAll();
            
            $data = array_map(function(Livre $livre) {
                return $this->serializeLivre($livre);
            }, $livres);

            return $this->json($data, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des livres', [
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
            $livre = $this->livreRepository->find($id);

            if (!$livre) {
                return $this->json(
                    ['error' => 'Livre non trouvé'],
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->json($this->serializeLivre($livre), Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du livre', [
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

            $requiredFields = ['titre', 'datePublication', 'auteurId', 'categorieId'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->json(
                        ['error' => "Le champ '$field' est requis"],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            $auteur = $this->auteurRepository->find($data['auteurId']);
            if (!$auteur) {
                return $this->json(
                    ['error' => 'Auteur non trouvé'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $categorie = $this->categorieRepository->find($data['categorieId']);
            if (!$categorie) {
                return $this->json(
                    ['error' => 'Catégorie non trouvée'],
                    Response::HTTP_NOT_FOUND
                );
            }

            try {
                $datePublication = new \DateTime($data['datePublication']);
            } catch (\Exception $e) {
                return $this->json(
                    ['error' => 'Format de date invalide. Utilisez le format YYYY-MM-DD'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $livre = new Livre();
            $livre->setTitre($data['titre'])
                  ->setDatePublication($datePublication)
                  ->setAuteur($auteur)
                  ->setCategorie($categorie)
                  ->setDisponible($data['disponible'] ?? true);

            $this->em->persist($livre);
            $this->em->flush();

            $this->logger->info('Livre créé avec succès', ['id' => $livre->getId()]);

            return $this->json(
                $this->serializeLivre($livre),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création du livre', [
                'error' => $e->getMessage()
            ]);
            return $this->json(
                ['error' => 'Une erreur est survenue'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $livre = $this->livreRepository->find($id);

            if (!$livre) {
                return $this->json(
                    ['error' => 'Livre non trouvé'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json(
                    ['error' => 'JSON invalide'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (isset($data['titre'])) {
                $livre->setTitre($data['titre']);
            }

            if (isset($data['datePublication'])) {
                try {
                    $datePublication = new \DateTime($data['datePublication']);
                    $livre->setDatePublication($datePublication);
                } catch (\Exception $e) {
                    return $this->json(
                        ['error' => 'Format de date invalide'],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            if (isset($data['auteurId'])) {
                $auteur = $this->auteurRepository->find($data['auteurId']);
                if (!$auteur) {
                    return $this->json(
                        ['error' => 'Auteur non trouvé'],
                        Response::HTTP_NOT_FOUND
                    );
                }
                $livre->setAuteur($auteur);
            }

            if (isset($data['categorieId'])) {
                $categorie = $this->categorieRepository->find($data['categorieId']);
                if (!$categorie) {
                    return $this->json(
                        ['error' => 'Catégorie non trouvée'],
                        Response::HTTP_NOT_FOUND
                    );
                }
                $livre->setCategorie($categorie);
            }

            if (isset($data['disponible'])) {
                $livre->setDisponible((bool) $data['disponible']);
            }

            $this->em->flush();

            $this->logger->info('Livre mis à jour avec succès', ['id' => $livre->getId()]);

            return $this->json($this->serializeLivre($livre), Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du livre', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->json(
                ['error' => 'Une erreur est survenue'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $livre = $this->livreRepository->find($id);

            if (!$livre) {
                return $this->json(
                    ['error' => 'Livre non trouvé'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $empruntsEnCours = $livre->getEmprunts()->filter(function($emprunt) {
                return $emprunt->isEnCours();
            });

            if (!$empruntsEnCours->isEmpty()) {
                return $this->json(
                    ['error' => 'Impossible de supprimer un livre avec des emprunts en cours'],
                    Response::HTTP_CONFLICT
                );
            }

            $this->em->remove($livre);
            $this->em->flush();

            $this->logger->info('Livre supprimé avec succès', ['id' => $id]);

            return $this->json(
                ['message' => 'Livre supprimé avec succès'],
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression du livre', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->json(
                ['error' => 'Une erreur est survenue'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function serializeLivre(Livre $livre): array
    {
        return [
            'id' => $livre->getId(),
            'titre' => $livre->getTitre(),
            'datePublication' => $livre->getDatePublication()->format('Y-m-d'),
            'disponible' => $livre->isDisponible(),
            'auteur' => [
                'id' => $livre->getAuteur()->getId(),
                'nom' => $livre->getAuteur()->getNom(),
                'prenom' => $livre->getAuteur()->getPrenom()
            ],
            'categorie' => [
                'id' => $livre->getCategorie()->getId(),
                'nom' => $livre->getCategorie()->getNom()
            ]
        ];
    }
}