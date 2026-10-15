<?php

declare(strict_types=1);

namespace App\Modules\AI\Infrastructure\Schema;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/** Model output is untrusted: it must validate against the task schema before anything uses it. */
final class JsonSchemaValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $schema
     * @return string|null the first error, or null when valid
     */
    public function validate(array $data, array $schema): ?string
    {
        $validator = new Validator;
        $validator->setMaxErrors(1);

        $result = $validator->validate(
            json_decode((string) json_encode($data, JSON_PRESERVE_ZERO_FRACTION)),
            (string) json_encode($schema),
        );

        if ($result->isValid()) {
            return null;
        }

        $errors = $result->error() !== null ? (new ErrorFormatter)->format($result->error(), false) : [];

        return (string) json_encode($errors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
