<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\Contracts;

use App\Modules\AI\Domain\Data\AiCompletion;
use App\Modules\AI\Domain\Data\AiResult;
use App\Modules\AI\Domain\Enums\ModelTier;
use App\Modules\AI\Domain\Exceptions\AiProviderFailed;

/**
 * An LLM vendor. Adapters translate a completion (system prompt, input and
 * the JSON schema of the expected answer) into the vendor's API and back.
 */
interface AiProvider
{
    public function name(): string;

    public function model(ModelTier $tier): string;

    /** Whether it can be called: credentials configured (or a user key given). */
    public function available(?string $userKey = null): bool;

    /** Whether users may bring their own key for this provider. */
    public function acceptsUserKeys(): bool;

    /**
     * @throws AiProviderFailed
     */
    public function complete(AiCompletion $completion): AiResult;
}
