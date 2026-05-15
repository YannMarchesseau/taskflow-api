<?php

namespace App\Controller;

use App\Entity\User;
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
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'firstName', 'lastName'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'test@test.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'Password123!'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'Yann'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Test'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User registered successfully'),
            new OA\Response(response: 400, description: 'Invalid payload or validation failed')
        ]
    )]
    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        $user = new User();
        $user->setEmail($data['email'] ?? '');
        $user->setFirstName($data['firstName'] ?? '');
        $user->setLastName($data['lastName'] ?? '');
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());

        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $data['password'] ?? ''
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
            'message' => 'User registered successfully'
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

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
        ]);
    }
}
