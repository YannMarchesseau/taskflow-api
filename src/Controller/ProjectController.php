<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects')]
final class ProjectController extends AbstractController
{
    #[Route('', name: 'project_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (in_array('ROLE_MANAGER', $user->getRoles(), true)) {
            $projects = $projectRepository->findAll();
        } else {
            $projects = $projectRepository->createQueryBuilder('p')
                ->leftJoin('p.members', 'm')
                ->andWhere('p.owner = :user OR m = :user')
                ->setParameter('user', $user)
                ->orderBy('p.id', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->json(array_map([$this, 'formatProject'], $projects));
    }

    #[Route('', name: 'project_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        if (empty($data['title']) || empty($data['startAt'])) {
            return $this->json([
                'message' => 'Missing required fields: title, startAt'
            ], 400);
        }

        try {
            $startAt = new \DateTimeImmutable($data['startAt']);
            $endAt = !empty($data['endAt']) ? new \DateTimeImmutable($data['endAt']) : null;
        } catch (\Exception) {
            return $this->json([
                'message' => 'Invalid date format'
            ], 400);
        }

        if ($endAt !== null && $endAt < $startAt) {
            return $this->json([
                'message' => 'endAt must be greater than or equal to startAt'
            ], 400);
        }

        $project = new Project();
        $project->setTitle($data['title']);
        $project->setDescription($data['description'] ?? null);
        $project->setStartAt($startAt);
        $project->setEndAt($endAt);
        $project->setStatus($data['status'] ?? 'active');
        $project->setOwner($user);
        $project->addMember($user);

        $entityManager->persist($project);
        $entityManager->flush();

        return $this->json($this->formatProject($project), 201);
    }

    #[Route('/{id}', name: 'project_show', methods: ['GET'])]
    public function show(Project $project): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->json($this->formatProject($project));
    }

    #[Route('/{id}', name: 'project_update', methods: ['PATCH'])]
    public function update(Project $project, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        if (array_key_exists('title', $data)) {
            if (empty($data['title'])) {
                return $this->json([
                    'message' => 'Title cannot be empty'
                ], 400);
            }

            $project->setTitle($data['title']);
        }

        if (array_key_exists('description', $data)) {
            $project->setDescription($data['description']);
        }

        if (array_key_exists('status', $data)) {
            if (!in_array($data['status'], ['active', 'archived'], true)) {
                return $this->json([
                    'message' => 'Invalid status'
                ], 400);
            }

            $project->setStatus($data['status']);
        }

        try {
            if (array_key_exists('startAt', $data)) {
                $project->setStartAt(new \DateTimeImmutable($data['startAt']));
            }

            if (array_key_exists('endAt', $data)) {
                $project->setEndAt(!empty($data['endAt']) ? new \DateTimeImmutable($data['endAt']) : null);
            }
        } catch (\Exception) {
            return $this->json([
                'message' => 'Invalid date format'
            ], 400);
        }

        if ($project->getEndAt() !== null && $project->getEndAt() < $project->getStartAt()) {
            return $this->json([
                'message' => 'endAt must be greater than or equal to startAt'
            ], 400);
        }

        $entityManager->flush();

        return $this->json($this->formatProject($project));
    }

    #[Route('/{id}', name: 'project_delete', methods: ['DELETE'])]
    public function delete(Project $project, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::DELETE, $project);

        $entityManager->remove($project);
        $entityManager->flush();

        return $this->json(null, 204);
    }

    private function formatProject(Project $project): array
    {
        return [
            'id' => $project->getId(),
            'title' => $project->getTitle(),
            'description' => $project->getDescription(),
            'startAt' => $project->getStartAt()?->format('Y-m-d'),
            'endAt' => $project->getEndAt()?->format('Y-m-d'),
            'status' => $project->getStatus(),
            'owner' => [
                'id' => $project->getOwner()->getId(),
                'email' => $project->getOwner()->getEmail(),
                'firstName' => $project->getOwner()->getFirstName(),
                'lastName' => $project->getOwner()->getLastName(),
            ],
            'members' => array_map(static fn (User $member) => [
                'id' => $member->getId(),
                'email' => $member->getEmail(),
                'firstName' => $member->getFirstName(),
                'lastName' => $member->getLastName(),
            ], $project->getMembers()->toArray()),
        ];
    }
}
