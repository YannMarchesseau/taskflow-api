<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TagController extends AbstractController
{
    #[Route('/projects/{id}/tags', name: 'tag_index', methods: ['GET'])]
    public function index(Project $project): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->json(array_map([$this, 'formatTag'], $project->getTags()->toArray()));
    }

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

    #[Route('/tags/{id}', name: 'tag_delete', methods: ['DELETE'])]
    public function delete(Tag $tag, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASKS, $tag->getProject());

        $entityManager->remove($tag);
        $entityManager->flush();

        return $this->json(null, 204);
    }

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
