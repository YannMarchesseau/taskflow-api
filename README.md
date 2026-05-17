# TaskFlow API

API REST sécurisée développée avec Symfony dans le cadre du workshop :

> Développer une application mobile sécurisée avec Swift et SwiftUI

L’API permet la gestion de projets collaboratifs, tâches, membres et tags avec authentification JWT.

---

# Stack technique

- Symfony 7
- PHP 8.3
- MariaDB / MySQL
- Doctrine ORM
- JWT (LexikJWTAuthenticationBundle)
- NelmioApiDocBundle / OpenAPI
- Voters Symfony
- API REST JSON

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

Créer un fichier `.env.local` :

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
  "email": "test@test.com",
  "password": "Password123!",
  "firstName": "Yann",
  "lastName": "Test"
}
```

---

## Connexion

```http
POST /auth/login
```

Payload exemple :

```json
{
  "email": "test@test.com",
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

# Endpoints principaux
<img width="895" height="1057" alt="TaskFlow-API" src="https://github.com/user-attachments/assets/bf0e029f-6924-40cf-8e57-fc1d9259e166" />


---

# Projects

## Gestion des projets

- `GET /projects`
- `POST /projects`
- `GET /projects/{id}`
- `PATCH /projects/{id}`
- `DELETE /projects/{id}`

## Gestion des membres

- `GET /projects/{id}/members`
- `POST /projects/{id}/members`
- `DELETE /projects/{id}/members/{userId}`

---

# Tasks

## Gestion des tâches

- `GET /projects/{id}/tasks`
- `POST /projects/{id}/tasks`
- `GET /tasks/{id}`
- `PATCH /tasks/{id}`
- `DELETE /tasks/{id}`

## États des tâches

- `POST /tasks/{id}/close`
- `POST /tasks/{id}/open`

## Assignation

- `PATCH /tasks/{id}/assign`

Payload exemple :

```json
{
  "assigneeId": 3
}
```

Pour retirer une assignation :

```json
{
  "assigneeId": null
}
```

---

# Tags

## Gestion des tags

- `GET /projects/{id}/tags`
- `POST /projects/{id}/tags`
- `PATCH /tags/{id}`
- `DELETE /tags/{id}`

## Association tags / tâches

- `POST /tasks/{id}/tags/{tagId}`
- `DELETE /tasks/{id}/tags/{tagId}`

---

# Sécurité

- Authentification JWT
- Stockage sécurisé des mots de passe
- Contrôle d’accès via Voters Symfony
- Permissions par projet
- Validation des payloads
- Gestion des erreurs HTTP
- Routes protégées

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

# Auteur

Yann Marchesseau

Projet réalisé dans le cadre du workshop Learning Campus :

> Développer une application mobile sécurisée avec Swift et SwiftUI
