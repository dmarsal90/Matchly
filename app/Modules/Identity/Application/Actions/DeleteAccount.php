<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Right to erasure: every module removes what the cascade cannot (files),
 * then deleting the user cascades through every table that references it.
 */
final readonly class DeleteAccount
{
    /**
     * @param  iterable<PersonalDataContributor>  $contributors
     */
    public function __construct(private iterable $contributors, private ConnectionInterface $db) {}

    public function handle(User $user): void
    {
        foreach ($this->contributors as $contributor) {
            $contributor->erase($user->id);
        }

        $this->db->transaction(function () use ($user): void {
            $this->db->table('sessions')->where('user_id', $user->id)->delete();
            $this->db->table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });
    }
}
