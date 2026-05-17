# TaskFlow API

API REST sécurisée développée avec Symfony dans le cadre du workshop :

> Développer une application mobile sécurisée avec Swift et SwiftUI

L’API permet la gestion collaborative de projets, tâches, membres et tags avec authentification JWT et contrôle d’accès avancé.

---

# Stack technique

- PHP 8.3
- Symfony 7
- Doctrine ORM
- MariaDB / MySQL
- JWT Authentication
- NelmioApiDocBundle / Swagger
- PHPUnit
- Monolog
- Voters Symfony

---

# Fonctionnalités

## Authentification

- Inscription utilisateur
- Connexion JWT
- Endpoint `/me`
- Gestion des rôles :
  - `ROLE_USER`
  - `ROLE_MANAGER`

---

## Projets

- CRUD projets
- Gestion des membres
- Contrôle d’accès par projet
- Statuts :
  - `active`
  - `archived`

---

## Tâches

- CRUD tâches
- Assignation utilisateur
- États :
  - `open`
  - `in_progress`
  - `closed`
- Priorités :
  - `low`
  - `medium`
  - `high`
- Filtres :
  - état
  - priorité
  - assigné
  - tag
- Pagination

---

## Tags

- CRUD tags
- Association / dissociation avec tâches

---

# Installation

## Cloner le projet

```bash
git clone https://github.com/YannMarchesseau/taskflow-api.git
cd taskflow-api
```

---

## Installer les dépendances

```bash
composer install
```

---

# Configuration

Créer un fichier `.env.local`

```env
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/taskflow_api?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
```

---

# Base de données

Créer la base :

```bash
php bin/console doctrine:database:create
```

Créer les migrations :

```bash
php bin/console make:migration
```

Exécuter les migrations :

```bash
php bin/console doctrine:migrations:migrate
```

---

# JWT

Générer les clés JWT :

```bash
php bin/console lexik:jwt:generate-keypair
```

---

# Lancer le serveur

```bash
symfony server:start
```

API disponible sur :

```text
https://127.0.0.1:8000
```

---

# Documentation Swagger / OpenAPI

Disponible sur :

```text
https://127.0.0.1:8000/api/doc
```

---

# Authentification

## Inscription

```http
POST /auth/register
```

Payload exemple :

```json
{
  "email": "manager@test.com",
  "password": "Password123!",
  "firstName": "Yann",
  "lastName": "Manager",
  "role": "ROLE_MANAGER"
}
```

---

## Connexion

```http
POST /auth/login
```

Payload :

```json
{
  "email": "manager@test.com",
  "password": "Password123!"
}
```

Réponse :

```json
{
  "token": "JWT_TOKEN"
}
```

---

## Profil utilisateur courant

```http
GET /me
```

---

# Endpoints principaux

<img width="1843" height="1414" alt="taskFlowAPIdoc" src="https://github.com/user-attachments/assets/578b65e5-eb74-4a6a-8505-084e3a04b250" />

---

# Projects

## Gestion projets

- `GET /projects`
- `POST /projects`
- `GET /projects/{id}`
- `PATCH /projects/{id}`
- `DELETE /projects/{id}`

## Membres projet

- `GET /projects/{id}/members`
- `POST /projects/{id}/members`
- `DELETE /projects/{id}/members/{userId}`

Payload exemple :

```json
{
  "email": "member@test.com"
}
```

ou :

```json
{
  "userId": 3
}
```

---

# Tasks

## Gestion tâches

- `GET /projects/{id}/tasks`
- `POST /projects/{id}/tasks`
- `GET /tasks/{id}`
- `PATCH /tasks/{id}`
- `DELETE /tasks/{id}`

---

## Changement d’état

- `POST /tasks/{id}/close`
- `POST /tasks/{id}/open`

---

## Assignation

- `PATCH /tasks/{id}/assign`

Payload :

```json
{
  "assigneeId": 3
}
```

Retirer une assignation :

```json
{
  "assigneeId": null
}
```

---

## Filtres disponibles

```http
GET /projects/{id}/tasks?state=open
GET /projects/{id}/tasks?priority=high
GET /projects/{id}/tasks?assigneeId=3
GET /projects/{id}/tasks?tagId=5
```

Pagination :

```http
GET /projects/{id}/tasks?page=1&limit=20
```

---

# Tags

## Gestion tags

- `GET /projects/{id}/tags`
- `POST /projects/{id}/tags`
- `PATCH /tags/{id}`
- `DELETE /tags/{id}`

## Association tag ↔ tâche

- `POST /tasks/{id}/tags/{tagId}`
- `DELETE /tasks/{id}/tags/{tagId}`

---

# Sécurité

- JWT Authentication
- Password hashing
- Symfony Voters
- Contrôle d’accès par projet
- Contrôle d’accès par tâche
- Validation des payloads
- Gestion des erreurs HTTP
- Protection des routes API

---

# Autorisations

| Action | ROLE_USER | ROLE_MANAGER |
|---|---|---|
| Voir projet membre | ✅ | ✅ |
| Créer projet | ✅ | ✅ |
| Modifier projet propriétaire | ✅ | ✅ |
| Gérer membres | propriétaire | ✅ |
| Créer tâche | propriétaire | ✅ |
| Modifier tâche assignée | ✅ | ✅ |
| Supprimer tâche | propriétaire | ✅ |
| Clore / rouvrir tâche assignée | ✅ | ✅ |

---

# Tests

Lancer les tests :

```bash
php bin/phpunit
```

---

# Déploiement

Le projet peut être déployé sur :

- Render
- Railway
- VPS Linux
- Docker

Variables importantes :

```env
APP_ENV=prod
APP_SECRET=
DATABASE_URL=
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=
```

---

# Workflow Git

- Branche `main`
- Branches `feature/...`
- Pull Requests
- Commits conventionnels

---

# Auteur

Yann Marchesseau

Projet réalisé dans le cadre du workshop :

> Développer une application mobile sécurisée avec Swift et SwiftUI
