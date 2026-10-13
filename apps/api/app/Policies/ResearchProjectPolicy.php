<?php

namespace App\Policies;

use App\Models\ResearchProject;
use App\Models\User;

class ResearchProjectPolicy
{
    public function view(User $user, ResearchProject $project): bool
    {
        return $user->id === $project->user_id;
    }

    public function update(User $user, ResearchProject $project): bool
    {
        return $this->view($user, $project);
    }

    public function delete(User $user, ResearchProject $project): bool
    {
        return $this->view($user, $project);
    }
}
