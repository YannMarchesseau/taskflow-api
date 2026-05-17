<?php

namespace App\Security\Voter;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TaskVoter extends Voter
{
    public const VIEW = 'TASK_VIEW';
    public const EDIT = 'TASK_EDIT';
    public const DELETE = 'TASK_DELETE';
    public const CHANGE_STATE = 'TASK_CHANGE_STATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::CHANGE_STATE,
        ], true) && $subject instanceof Task;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            $vote?->addReason('The user must be logged in to access this task.');

            return false;
        }

        /** @var Task $task */
        $task = $subject;

        if ($user->isManager()) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($task, $user),
            self::EDIT => $task->canBeManagedBy($user) || $task->isAssignedTo($user),
            self::DELETE => $task->canBeManagedBy($user),
            self::CHANGE_STATE => $task->canStateBeChangedBy($user),
            default => false,
        };
    }

    private function canView(Task $task, User $user): bool
    {
        $project = $task->getProject();

        return $project !== null && $project->hasMember($user);
    }
}
