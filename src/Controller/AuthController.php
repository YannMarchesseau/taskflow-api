<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Authentication')]
final class AuthController extends AbstractController
{
    #[OA\Post(
        path: '/auth/register',
        summary: 'Register a new user',
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 201, description: 'User registered successfully'),
            new OA\Response(response: 400, description: 'Invalid payload or validation failed')
        ]
    )]
    #[OA\RequestBody(
        required: true,
        description: 'User registration payload',
        content: new OA\JsonContent(
            type: 'object',
            required: ['email', 'password', 'firstName', 'lastName'],
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'test@test.com'),
                new OA\Property(property: 'password', type: 'string', example: 'Password123!'),
                new OA\Property(property: 'firstName', type: 'string', example: 'Yann'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Test'),
                new OA\Property(property: 'role', type: 'string', enum: ['ROLE_USER', 'ROLE_MANAGER'], example: 'ROLE_USER')
            ]
        )
    )]
    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        UserRepository $userRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = $data['role'] ?? 'ROLE_USER';

        if (!in_array($role, ['ROLE_USER', 'ROLE_MANAGER'], true)) {
            return $this->json([
                'message' => 'Invalid role. Allowed roles are ROLE_USER and ROLE_MANAGER.'
            ], 400);
        }

        if ($email !== '' && $userRepository->findOneBy(['email' => $email]) instanceof User) {
            return $this->json([
                'message' => 'Email already exists'
            ], 409);
        }

        if (strlen($password) < 8) {
            return $this->json([
                'message' => 'Password must contain at least 8 characters'
            ], 400);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName(trim((string) ($data['firstName'] ?? '')));
        $user->setLastName(trim((string) ($data['lastName'] ?? '')));
        $user->setRoles([$role]);

        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $password
        );

        $user->setPassword($hashedPassword);

        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            $validationErrors = [];

            foreach ($errors as $error) {
                $validationErrors[] = $error->getMessage();
            }

            return $this->json([
                'message' => 'Validation failed',
                'errors' => $validationErrors
            ], 400);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'message' => 'User registered successfully',
            'user' => $this->formatUser($user),
        ], 201);
    }

    #[OA\Get(
        path: '/me',
        summary: 'Get current authenticated user profile',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current user profile',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'test@test.com'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Yann'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Test'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        return $this->json($this->formatUser($user));
    }


    #[OA\Get(
        path: '/users',
        summary: 'List users',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(response: 200, description: 'Users list'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied')
        ]
    )]
    #[Route('/users', name: 'user_index', methods: ['GET'])]
    public function users(UserRepository $userRepository): JsonResponse
    {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!$currentUser->isManager()) {
            return $this->json([
                'message' => 'Access denied'
            ], 403);
        }

        $users = $userRepository->findBy([], ['email' => 'ASC']);

        return $this->json(array_map([$this, 'formatUser'], $users));
    }

    #[OA\Patch(
        path: '/users/{id}/role',
        summary: 'Update user role',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'User role updated'),
            new OA\Response(response: 400, description: 'Invalid payload'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Role update payload',
        content: new OA\JsonContent(
            type: 'object',
            required: ['role'],
            properties: [
                new OA\Property(property: 'role', type: 'string', enum: ['ROLE_USER', 'ROLE_MANAGER'], example: 'ROLE_MANAGER')
            ]
        )
    )]
    #[Route('/users/{id}/role', name: 'user_update_role', methods: ['PATCH'])]
    public function updateUserRole(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!$currentUser->isManager()) {
            return $this->json([
                'message' => 'Access denied'
            ], 403);
        }

        $user = $userRepository->find($id);

        if (!$user instanceof User) {
            return $this->json([
                'message' => 'User not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty($data['role'])) {
            return $this->json([
                'message' => 'Missing required field: role'
            ], 400);
        }

        $role = $data['role'];

        if (!in_array($role, ['ROLE_USER', 'ROLE_MANAGER'], true)) {
            return $this->json([
                'message' => 'Invalid role. Allowed roles are ROLE_USER and ROLE_MANAGER.'
            ], 400);
        }

        $user->setRoles([$role]);
        $entityManager->flush();

        return $this->json($this->formatUser($user));
    }

    #[OA\Delete(
        path: '/users/{id}',
        summary: 'Delete a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'User deleted'),
            new OA\Response(response: 400, description: 'Invalid operation'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
    #[Route('/users/{id}', name: 'user_delete', methods: ['DELETE'])]
    public function deleteUser(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!$currentUser->isManager()) {
            return $this->json([
                'message' => 'Access denied'
            ], 403);
        }

        $user = $userRepository->find($id);

        if (!$user instanceof User) {
            return $this->json([
                'message' => 'User not found'
            ], 404);
        }

        if ($currentUser->getId() === $user->getId()) {
            return $this->json([
                'message' => 'You cannot delete your own account'
            ], 400);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return $this->json(null, 204);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM),
        ];
    }
}
