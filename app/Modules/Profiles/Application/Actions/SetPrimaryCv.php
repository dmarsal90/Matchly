<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class SetPrimaryCv
{
    public function __construct(private ConnectionInterface $db, private Dispatcher $events) {}

    public function handle(CvDocument $document): void
    {
        $this->db->transaction(function () use ($document): void {
            CvDocument::query()->where('user_id', $document->user_id)->update(['is_primary' => false]);
            $document->forceFill(['is_primary' => true])->save();
        });

        $this->events->dispatch(new ProfileChanged($document->user_id));
    }
}
