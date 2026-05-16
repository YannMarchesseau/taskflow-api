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

# Documentation Swagger

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

Exemple payload :

```json
{
  "email": "test@test.com",
  "password": "Password123!",
  "firstName": "Yann",
  "lastName": "Test"
}
```

---

## Login

```http
POST /auth/login
```

Exemple payload :

```json
{
  "email": "test@test.com",
  "password": "Password123!"
}
```

Retour :

```json
{
  "token": "JWT_TOKEN"
}
```

---

# Endpoints principaux
<img width="1843" height="1414" alt="taskFlowAPIdoc" src="https://github.com/user-attachments/assets/578b65e5-eb74-4a6a-8505-084e3a04b250" />

## Projects

- `GET /projects`
- `POST /projects`
- `GET /projects/{id}`
- `PATCH /projects/{id}`
- `DELETE /projects/{id}`

## Tasks

- `GET /projects/{id}/tasks`
- `POST /projects/{id}/tasks`
- `GET /tasks/{id}`
- `PATCH /tasks/{id}`
- `DELETE /tasks/{id}`
- `POST /tasks/{id}/close`
- `POST /tasks/{id}/open`

## Tags

- `GET /projects/{id}/tags`
- `POST /projects/{id}/tags`
- `PATCH /tags/{id}`
- `DELETE /tags/{id}`
- `POST /tasks/{id}/tags/{tagId}`
- `DELETE /tasks/{id}/tags/{tagId}`

---

# Sécurité

- Authentification JWT
- Contrôle d’accès via Voters
- Permissions par projet
- Validation des données
- Routes protégées

---

# Tests

Lancer les tests :

```bash
php bin/phpunit
```

---

# Auteur

Projet réalisé dans le cadre d'un workshop Leaning Campus "Développer une application mobile sécurisée avec SWIFT et SWIFTUI" T.
