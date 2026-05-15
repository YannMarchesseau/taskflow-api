<?php

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const DELETE = 'PROJECT_DELETE';
    public const MANAGE_MEMBERS = 'PROJECT_MANAGE_MEMBERS';
    public const MANAGE_TASKS = 'PROJECT_MANAGE_TASKS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::MANAGE_MEMBERS,
            self::MANAGE_TASKS,
        ], true) && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            $vote?->addReason('The user must be logged in to access this resource.');

            return false;
        }

        /** @var Project $project */
        $project = $subject;

        if (in_array('ROLE_MANAGER', $user->getRoles(), true)) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($project, $user),
            self::EDIT,
            self::DELETE,
            self::MANAGE_MEMBERS,
            self::MANAGE_TASKS => $this->isOwner($project, $user),
            default => false,
        };
    }

    private function canView(Project $project, User $user): bool
    {
        return $this->isOwner($project, $user) || $project->getMembers()->contains($user);
    }

    private function isOwner(Project $project, User $user): bool
    {
        return $project->getOwner() === $user;
    }
}
