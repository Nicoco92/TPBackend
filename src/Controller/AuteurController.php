<?php

namespace App\Controller;

use App\Entity\Auteur;
use App\Repository\AuteurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/auteurs', name: 'api_auteur_')]
class AuteurController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuteurRepository $auteurRepository,
        private LoggerInterface $logger
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $auteurs = $this->auteurRepository->findAll();
            
            $data = array_map(function(Auteur $auteur) {
                return $this->serializeAuteur($auteur);
            }, $auteurs);

            return $this->json($data, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des auteurs', [
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
            $auteur = $this->auteurRepository->find($id);

            if (!$auteur) {
                return $this->json(
                    ['error' => 'Auteur non trouvé'],
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->json($this->serializeAuteur($auteur), Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération de l\'auteur', [
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

            $auteur = new Auteur();
            $auteur->setNom($data['nom'])
                   ->setPrenom($data['prenom']);

            if (isset($data['biographie'])) {
                $auteur->setBiographie($data['biographie']);
            }

            if (isset($data['dateNaissance'])) {
                try {
                    $dateNaissance = new \DateTime($data['dateNaissance']);
                    $auteur->setDateNaissance($dateNaissance);
                } catch (\Exception $e) {
                    return $this->json(
                        ['error' => 'Format de date invalide. Utilisez le format YYYY-MM-DD'],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            $this->em->persist($auteur);
            $this->em->flush();

            $this->logger->info('Auteur créé avec succès', ['id' => $auteur->getId()]);

            return $this->json(
                $this->serializeAuteur($auteur),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création de l\'auteur', [
                'error' => $e->getMessage()
            ]);
            return $this->json(
                ['error' => 'Une erreur est survenue'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function serializeAuteur(Auteur $auteur): array
    {
        return [
            'id' => $auteur->getId(),
            'nom' => $auteur->getNom(),
            'prenom' => $auteur->getPrenom(),
            'biographie' => $auteur->getBiographie(),
            'dateNaissance' => $auteur->getDateNaissance()?->format('Y-m-d')
        ];
    }
}