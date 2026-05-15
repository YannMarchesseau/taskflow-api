<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Projects')]
#[Route('/projects')]
final class ProjectController extends AbstractController
{
    #[OA\Get(
        path: '/projects',
        summary: 'List projects visible by the authenticated user',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Project list'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
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

    #[OA\Post(
        path: '/projects',
        summary: 'Create a new project',
        security: [['bearerAuth' => []]],
        tags: ['Projects'],
        responses: [
            new OA\Response(response: 201, description: 'Project created'),
            new OA\Response(response: 400, description: 'Invalid payload'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Project data',
        content: new OA\JsonContent(
            type: 'object',
            required: ['title', 'startAt'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Projet TaskFlow'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Premier projet de test'),
                new OA\Property(property: 'startAt', type: 'string', format: 'date', example: '2026-05-15'),
                new OA\Property(property: 'endAt', type: 'string', format: 'date', nullable: true, example: '2026-06-15'),
                new OA\Property(property: 'status', type: 'string', enum: ['active', 'archived'], example: 'active')
            ]
        )
    )]
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

    #[OA\Get(
        path: '/projects/{id}',
        summary: 'Get one project',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Project details'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Project not found')
        ]
    )]
    #[Route('/{id}', name: 'project_show', methods: ['GET'])]
    public function show(Project $project): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->json($this->formatProject($project));
    }

    #[OA\Patch(
        path: '/projects/{id}',
        summary: 'Update a project',
        security: [['bearerAuth' => []]],
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Project updated'),
            new OA\Response(response: 400, description: 'Invalid payload'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Project not found')
        ]
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Fields to update',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Projet TaskFlow modifié'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Description mise à jour'),
                new OA\Property(property: 'startAt', type: 'string', format: 'date', example: '2026-05-15'),
                new OA\Property(property: 'endAt', type: 'string', format: 'date', nullable: true, example: '2026-06-15'),
                new OA\Property(property: 'status', type: 'string', enum: ['active', 'archived'], example: 'archived')
            ]
        )
    )]
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

    #[OA\Delete(
        path: '/projects/{id}',
        summary: 'Delete a project',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Project deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Project not found')
        ]
    )]
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
