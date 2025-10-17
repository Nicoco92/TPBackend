<?php

namespace App\Controller;

use App\Entity\Emprunt;
use App\Repository\LivreRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\EmpruntRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/emprunts', name: 'api_emprunts_')]
class EmpruntController extends AbstractController
{
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        LivreRepository $livreRepo,
        UtilisateurRepository $userRepo,
        EmpruntRepository $empruntRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $livreId = $data['livre_id'] ?? null;
        $userId = $data['utilisateur_id'] ?? null;

        if (!$livreId || !$userId) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }

        $livre = $livreRepo->find($livreId);
        $utilisateur = $userRepo->find($userId);

        if (!$livre || !$utilisateur) {
            return $this->json(['error' => 'Livre ou utilisateur introuvable'], 404);
        }

        if (!$livre->isDisponible()) {
            return $this->json(['error' => 'Ce livre est déjà emprunté'], 400);
        }

        $nbEmpruntsActifs = $empruntRepo->count([
            'utilisateur' => $utilisateur,
            'dateRetour' => null
        ]);

        if ($nbEmpruntsActifs >= 4) {
            return $this->json(['error' => 'Limite de 4 emprunts atteinte'], 400);
        }

        $emprunt = new Emprunt();
        $emprunt->setLivre($livre);
        $emprunt->setUtilisateur($utilisateur);
        $emprunt->setDateEmprunt(new \DateTime());

        $livre->setDisponible(false);

        $em->persist($emprunt);
        $em->flush();

        return $this->json([
        'message' => 'Livre emprunté avec succès',
        'emprunt_id' => $emprunt->getId(),
        'livre_id' => $livre->getId(),
        'utilisateur_id' => $utilisateur->getId()
        ], 201);
    }
    #[Route('/{id}/rendre', name: 'rendre', methods: ['PATCH'])]
    public function rendre(
        Emprunt $emprunt,
        EntityManagerInterface $em
    ): JsonResponse {
    
        if (!$emprunt->isEnCours()) {
            return $this->json([
                'error' => 'Ce livre a déjà été rendu.'
            ], 400);
        }

        
        $emprunt->setDateRetour(new \DateTime());
        $livre = $emprunt->getLivre();
        $livre->setDisponible(true);

        $em->flush();

        return $this->json([
            'message' => sprintf('Le livre "%s" a bien été rendu.', $livre->getTitre()),
            'dateRetour' => $emprunt->getDateRetour()->format('Y-m-d H:i:s')
        ], 200);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(EmpruntRepository $repo): JsonResponse
    {
    $emprunts = $repo->findAll();

    $data = [];
    foreach ($emprunts as $emprunt) {
        $data[] = [
            'id' => $emprunt->getId(),
            'livre' => $emprunt->getLivre()->getTitre(),
            'utilisateur' => $emprunt->getUtilisateur()->getNom(),
            'dateEmprunt' => $emprunt->getDateEmprunt()->format('Y-m-d H:i:s'),
            'dateRetour' => $emprunt->getDateRetour()?->format('Y-m-d H:i:s')
        ];
    }

    return $this->json($data);
    }
}