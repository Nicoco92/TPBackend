<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/utilisateurs', name: 'api_utilisateur_')]
class UtilisateurController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UtilisateurRepository $utilisateurRepository,
        private LoggerInterface $logger
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $utilisateurs = $this->utilisateurRepository->findAll();
            
            $data = array_map(function(Utilisateur $utilisateur) {
                return $this->serializeUtilisateur($utilisateur);
            }, $utilisateurs);

            return $this->json($data, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des utilisateurs', [
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
            $utilisateur = $this->utilisateurRepository->find($id);

            if (!$utilisateur) {
                return $this->json(
                    ['error' => 'Utilisateur non trouvé'],
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->json($this->serializeUtilisateur($utilisateur), Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération de l\'utilisateur', [
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

            $requiredFields = ['nom', 'prenom'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->json(
                        ['error' => "Le champ '$field' est requis"],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            $utilisateur = new Utilisateur();
            $utilisateur->setNom($data['nom'])
                       ->setPrenom($data['prenom']);

            $this->em->persist($utilisateur);
            $this->em->flush();

            $this->logger->info('Utilisateur créé avec succès', ['id' => $utilisateur->getId()]);

            return $this->json(
                $this->serializeUtilisateur($utilisateur),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création de l\'utilisateur', [
                'error' => $e->getMessage()
            ]);
            return $this->json(
                ['error' => 'Une erreur est survenue'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function serializeUtilisateur(Utilisateur $utilisateur): array
    {
        return [
            'id' => $utilisateur->getId(),
            'nom' => $utilisateur->getNom(),
            'prenom' => $utilisateur->getPrenom()
        ];
    }
    
}