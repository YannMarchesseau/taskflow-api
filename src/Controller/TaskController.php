<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Tasks')]
final class TaskController extends AbstractController
{
    #[OA\Get(
        path: '/projects/{id}/tasks',
        summary: 'List tasks for a project',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Project ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Task list'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Project not found')
        ]
    )]
    #[Route('/projects/{id}/tasks', name: 'task_index', methods: ['GET'])]
    public function index(Project $project): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->json(array_map([$this, 'formatTask'], $project->getTasks()->toArray()));
    }

    #[OA\Post(
        path: '/projects/{id}/tasks',
        summary: 'Create a task in a project',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Project ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 201, description: 'Task created'),
            new OA\Response(response: 400, description: 'Invalid payload'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Project or assignee not found')
        ]
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Task data',
        content: new OA\JsonContent(
            type: 'object',
            required: ['title'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Créer le CRUD Task'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Première tâche du projet'),
                new OA\Property(property: 'dueAt', type: 'string', format: 'date', nullable: true, example: '2026-05-20'),
                new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high'], example: 'high'),
                new OA\Property(property: 'state', type: 'string', enum: ['open', 'in_progress', 'closed'], example: 'open'),
                new OA\Property(property: 'assigneeId', type: 'integer', nullable: true, example: 1)
            ]
        )
    )]
    #[Route('/projects/{id}/tasks', name: 'task_create', methods: ['POST'])]
    public function create(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASKS, $project);

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        if (empty($data['title'])) {
            return $this->json([
                'message' => 'Missing required field: title'
            ], 400);
        }

        $priority = $data['priority'] ?? 'medium';
        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            return $this->json([
                'message' => 'Invalid priority'
            ], 400);
        }

        $state = $data['state'] ?? 'open';
        if (!in_array($state, ['open', 'in_progress', 'closed'], true)) {
            return $this->json([
                'message' => 'Invalid state'
            ], 400);
        }

        try {
            $dueAt = !empty($data['dueAt']) ? new \DateTimeImmutable($data['dueAt']) : null;
        } catch (\Exception) {
            return $this->json([
                'message' => 'Invalid dueAt date format'
            ], 400);
        }

        $assignee = null;
        if (!empty($data['assigneeId'])) {
            $assignee = $userRepository->find($data['assigneeId']);

            if (!$assignee) {
                return $this->json([
                    'message' => 'Assignee not found'
                ], 404);
            }

            if (!$project->getMembers()->contains($assignee) && $project->getOwner() !== $assignee) {
                return $this->json([
                    'message' => 'Assignee must be a project member'
                ], 400);
            }
        }

        $task = new Task();
        $task->setTitle($data['title']);
        $task->setDescription($data['description'] ?? null);
        $task->setDueAt($dueAt);
        $task->setPriority($priority);
        $task->setState($state);
        $task->setProject($project);
        $task->setAssignee($assignee);

        $entityManager->persist($task);
        $entityManager->flush();

        return $this->json($this->formatTask($task), 201);
    }

    #[OA\Get(
        path: '/tasks/{id}',
        summary: 'Get one task',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Task ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Task details'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Task not found')
        ]
    )]
    #[Route('/tasks/{id}', name: 'task_show', methods: ['GET'])]
    public function show(Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $task->getProject());

        return $this->json($this->formatTask($task));
    }

    #[OA\Patch(
        path: '/tasks/{id}',
        summary: 'Update a task',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Task ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Task updated'),
            new OA\Response(response: 400, description: 'Invalid payload'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Task or assignee not found')
        ]
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Fields to update',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Créer le CRUD Task modifié'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Description mise à jour'),
                new OA\Property(property: 'dueAt', type: 'string', format: 'date', nullable: true, example: '2026-05-25'),
                new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high'], example: 'medium'),
                new OA\Property(property: 'state', type: 'string', enum: ['open', 'in_progress', 'closed'], example: 'in_progress'),
                new OA\Property(property: 'assigneeId', type: 'integer', nullable: true, example: 1)
            ]
        )
    )]
    #[Route('/tasks/{id}', name: 'task_update', methods: ['PATCH'])]
    public function update(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $project = $task->getProject();

        $isManager = in_array('ROLE_MANAGER', $user->getRoles(), true);
        $isOwner = $project->getOwner() === $user;
        $isAssignee = $task->getAssignee() === $user;

        if (!$isManager && !$isOwner && !$isAssignee) {
            return $this->json([
                'message' => 'Access denied'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        if (array_key_exists('title', $data)) {
            if (!$isManager && !$isOwner) {
                return $this->json([
                    'message' => 'Only the project owner can update the title'
                ], 403);
            }

            if (empty($data['title'])) {
                return $this->json([
                    'message' => 'Title cannot be empty'
                ], 400);
            }

            $task->setTitle($data['title']);
        }

        if (array_key_exists('description', $data)) {
            if (!$isManager && !$isOwner) {
                return $this->json([
                    'message' => 'Only the project owner can update the description'
                ], 403);
            }

            $task->setDescription($data['description']);
        }

        if (array_key_exists('priority', $data)) {
            if (!$isManager && !$isOwner) {
                return $this->json([
                    'message' => 'Only the project owner can update the priority'
                ], 403);
            }

            if (!in_array($data['priority'], ['low', 'medium', 'high'], true)) {
                return $this->json([
                    'message' => 'Invalid priority'
                ], 400);
            }

            $task->setPriority($data['priority']);
        }

        if (array_key_exists('state', $data)) {
            if (!$isManager && !$isOwner && !$isAssignee) {
                return $this->json([
                    'message' => 'Only the project owner or assignee can update the state'
                ], 403);
            }

            if (!in_array($data['state'], ['open', 'in_progress', 'closed'], true)) {
                return $this->json([
                    'message' => 'Invalid state'
                ], 400);
            }

            $task->setState($data['state']);
        }

        if (array_key_exists('dueAt', $data)) {
            if (!$isManager && !$isOwner) {
                return $this->json([
                    'message' => 'Only the project owner can update the due date'
                ], 403);
            }

            try {
                $task->setDueAt(!empty($data['dueAt']) ? new \DateTimeImmutable($data['dueAt']) : null);
            } catch (\Exception) {
                return $this->json([
                    'message' => 'Invalid dueAt date format'
                ], 400);
            }
        }

        if (array_key_exists('assigneeId', $data)) {
            if (!$isManager && !$isOwner) {
                return $this->json([
                    'message' => 'Only the project owner can update the assignee'
                ], 403);
            }

            $assignee = null;
            if (!empty($data['assigneeId'])) {
                $assignee = $userRepository->find($data['assigneeId']);

                if (!$assignee) {
                    return $this->json([
                        'message' => 'Assignee not found'
                    ], 404);
                }

                if (!$project->getMembers()->contains($assignee) && $project->getOwner() !== $assignee) {
                    return $this->json([
                        'message' => 'Assignee must be a project member'
                    ], 400);
                }
            }

            $task->setAssignee($assignee);
        }

        $entityManager->flush();

        return $this->json($this->formatTask($task));
    }

    #[OA\Patch(
        path: '/tasks/{id}/assign',
        summary: 'Assign a task to a project member',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Task ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Task assigned'),
            new OA\Response(response: 400, description: 'Invalid payload'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Task or assignee not found')
        ]
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Assignee data. Send null to remove assignee.',
        content: new OA\JsonContent(
            type: 'object',
            required: ['assigneeId'],
            properties: [
                new OA\Property(property: 'assigneeId', type: 'integer', nullable: true, example: 3)
            ]
        )
    )]
    #[Route('/tasks/{id}/assign', name: 'task_assign', methods: ['PATCH'])]
    public function assign(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $project = $task->getProject();

        $isManager = in_array('ROLE_MANAGER', $user->getRoles(), true);
        $isOwner = $project->getOwner() === $user;

        if (!$isManager && !$isOwner) {
            return $this->json([
                'message' => 'Only the project owner can update the assignee'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !array_key_exists('assigneeId', $data)) {
            return $this->json([
                'message' => 'Missing required field: assigneeId'
            ], 400);
        }

        $assignee = null;

        if (!empty($data['assigneeId'])) {
            $assignee = $userRepository->find((int) $data['assigneeId']);

            if (!$assignee instanceof User) {
                return $this->json([
                    'message' => 'Assignee not found'
                ], 404);
            }

            if (!$project->getMembers()->contains($assignee) && $project->getOwner() !== $assignee) {
                return $this->json([
                    'message' => 'Assignee must be a project member'
                ], 400);
            }
        }

        $task->setAssignee($assignee);
        $entityManager->flush();

        return $this->json($this->formatTask($task));
    }

    #[OA\Delete(
        path: '/tasks/{id}',
        summary: 'Delete a task',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Task ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Task deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Task not found')
        ]
    )]
    #[Route('/tasks/{id}', name: 'task_delete', methods: ['DELETE'])]
    public function delete(Task $task, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASKS, $task->getProject());

        $entityManager->remove($task);
        $entityManager->flush();

        return $this->json(null, 204);
    }

    #[OA\Post(
        path: '/tasks/{id}/close',
        summary: 'Close a task',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Task ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Task closed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Task not found')
        ]
    )]
    #[Route('/tasks/{id}/close', name: 'task_close', methods: ['POST'])]
    public function close(Task $task, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->canChangeState($task)) {
            return $this->json([
                'message' => 'Access denied'
            ], 403);
        }

        $task->setState('closed');
        $entityManager->flush();

        return $this->json($this->formatTask($task));
    }

    #[OA\Post(
        path: '/tasks/{id}/open',
        summary: 'Reopen a task',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Task ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Task reopened'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Task not found')
        ]
    )]
    #[Route('/tasks/{id}/open', name: 'task_open', methods: ['POST'])]
    public function open(Task $task, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->canChangeState($task)) {
            return $this->json([
                'message' => 'Access denied'
            ], 403);
        }

        $task->setState('open');
        $entityManager->flush();

        return $this->json($this->formatTask($task));
    }

    private function canChangeState(Task $task): bool
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $task->getProject();

        return in_array('ROLE_MANAGER', $user->getRoles(), true)
            || $project->getOwner() === $user
            || $task->getAssignee() === $user;
    }

    private function formatTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'dueAt' => $task->getDueAt()?->format('Y-m-d'),
            'priority' => $task->getPriority(),
            'state' => $task->getState(),
            'project' => [
                'id' => $task->getProject()->getId(),
                'title' => $task->getProject()->getTitle(),
            ],
            'assignee' => $task->getAssignee() ? [
                'id' => $task->getAssignee()->getId(),
                'email' => $task->getAssignee()->getEmail(),
                'firstName' => $task->getAssignee()->getFirstName(),
                'lastName' => $task->getAssignee()->getLastName(),
            ] : null,
            'tags' => array_map(static fn ($tag) => [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
            ], $task->getTags()->toArray()),
        ];
    }
}
