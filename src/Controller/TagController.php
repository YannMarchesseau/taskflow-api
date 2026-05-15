<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Tags')]
final class TagController extends AbstractController
{
    #[OA\Get(
        path: '/projects/{id}/tags',
        summary: 'List tags for a project',
        tags: ['Tags'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tag list'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Project not found')
        ]
    )]
    #[Route('/projects/{id}/tags', name: 'tag_index', methods: ['GET'])]
    public function index(Project $project): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->json(array_map([$this, 'formatTag'], $project->getTags()->toArray()));
    }

    #[OA\Post(
        path: '/projects/{id}/tags',
        summary: 'Create a tag in a project',
        tags: ['Tags'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 201, description: 'Tag created'),
            new OA\Response(response: 400, description: 'Invalid payload'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Project not found'),
            new OA\Response(response: 409, description: 'Tag already exists')
        ]
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Tag data',
        content: new OA\JsonContent(
            type: 'object',
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'backend')
            ]
        )
    )]
    #[Route('/projects/{id}/tags', name: 'tag_create', methods: ['POST'])]
    public function create(Project $project, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASKS, $project);

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        if (empty($data['name'])) {
            return $this->json([
                'message' => 'Missing required field: name'
            ], 400);
        }

        foreach ($project->getTags() as $existingTag) {
            if (mb_strtolower($existingTag->getName()) === mb_strtolower($data['name'])) {
                return $this->json([
                    'message' => 'Tag already exists for this project'
                ], 409);
            }
        }

        $tag = new Tag();
        $tag->setName($data['name']);
        $tag->setProject($project);

        $entityManager->persist($tag);
        $entityManager->flush();

        return $this->json($this->formatTag($tag), 201);
    }

    #[OA\Patch(
        path: '/tags/{id}',
        summary: 'Update a tag',
        tags: ['Tags'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tag updated'),
            new OA\Response(response: 400, description: 'Invalid payload'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Tag not found'),
            new OA\Response(response: 409, description: 'Tag already exists')
        ]
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Fields to update',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'frontend')
            ]
        )
    )]
    #[Route('/tags/{id}', name: 'tag_update', methods: ['PATCH'])]
    public function update(Tag $tag, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASKS, $tag->getProject());

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        if (array_key_exists('name', $data)) {
            if (empty($data['name'])) {
                return $this->json([
                    'message' => 'Name cannot be empty'
                ], 400);
            }

            foreach ($tag->getProject()->getTags() as $existingTag) {
                if ($existingTag !== $tag && mb_strtolower($existingTag->getName()) === mb_strtolower($data['name'])) {
                    return $this->json([
                        'message' => 'Tag already exists for this project'
                    ], 409);
                }
            }

            $tag->setName($data['name']);
        }

        $entityManager->flush();

        return $this->json($this->formatTag($tag));
    }

    #[OA\Delete(
        path: '/tags/{id}',
        summary: 'Delete a tag',
        tags: ['Tags'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Tag deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Tag not found')
        ]
    )]
    #[Route('/tags/{id}', name: 'tag_delete', methods: ['DELETE'])]
    public function delete(Tag $tag, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASKS, $tag->getProject());

        $entityManager->remove($tag);
        $entityManager->flush();

        return $this->json(null, 204);
    }

    #[OA\Post(
        path: '/tasks/{id}/tags/{tagId}',
        summary: 'Attach a tag to a task',
        tags: ['Tags'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Task ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tagId', in: 'path', required: true, description: 'Tag ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tag attached to task'),
            new OA\Response(response: 400, description: 'Tag does not belong to the task project'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Task or tag not found')
        ]
    )]
    #[Route('/tasks/{id}/tags/{tagId}', name: 'task_add_tag', methods: ['POST'])]
    public function addTagToTask(Task $task, int $tagId, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASKS, $task->getProject());

        $tag = $entityManager->getRepository(Tag::class)->find($tagId);

        if (!$tag) {
            return $this->json([
                'message' => 'Tag not found'
            ], 404);
        }

        if ($tag->getProject() !== $task->getProject()) {
            return $this->json([
                'message' => 'Tag does not belong to the task project'
            ], 400);
        }

        $task->addTag($tag);
        $entityManager->flush();

        return $this->json([
            'message' => 'Tag added to task successfully',
            'taskId' => $task->getId(),
            'tag' => $this->formatTag($tag),
        ]);
    }

    #[OA\Delete(
        path: '/tasks/{id}/tags/{tagId}',
        summary: 'Detach a tag from a task',
        tags: ['Tags'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Task ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tagId', in: 'path', required: true, description: 'Tag ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Tag detached from task'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Task or tag not found')
        ]
    )]
    #[Route('/tasks/{id}/tags/{tagId}', name: 'task_remove_tag', methods: ['DELETE'])]
    public function removeTagFromTask(Task $task, int $tagId, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASKS, $task->getProject());

        $tag = $entityManager->getRepository(Tag::class)->find($tagId);

        if (!$tag) {
            return $this->json([
                'message' => 'Tag not found'
            ], 404);
        }

        $task->removeTag($tag);
        $entityManager->flush();

        return $this->json(null, 204);
    }

    private function formatTag(Tag $tag): array
    {
        return [
            'id' => $tag->getId(),
            'name' => $tag->getName(),
            'project' => [
                'id' => $tag->getProject()->getId(),
                'title' => $tag->getProject()->getTitle(),
            ],
        ];
    }
}
