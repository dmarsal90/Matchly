# Añadir un proveedor de IA

La aplicación nunca llama a un proveedor directamente: todo pasa por `AiGateway` (`app/Modules/AI/Application`). Para cada tarea (`AiTask`):

1. Carga el prompt versionado (`app/Modules/AI/Prompts/v1/<tarea>.md`) y su JSON Schema.
2. Prueba los proveedores en el orden de `AI_FALLBACK_ORDER`. Si el usuario guardó su propia clave, esa va primero y no consume el presupuesto del servidor.
3. Valida la respuesta contra el esquema, con un reintento si no lo cumple.
4. Registra los tokens y el coste en `ai_usages`, y aplica el presupuesto mensual y el límite por hora.

Un proveedor solo tiene que hacer una cosa: recibir un `AiCompletion` (prompt de sistema, entrada, esquema, modelo y máximo de tokens) y devolver un `AiResult` (los datos JSON decodificados y el consumo de tokens).

## Proveedores incluidos, gratis y de pago

Vienen configurados Anthropic y OpenAI (de pago, mejor redacción), DeepSeek (muy barato), Gemini, Groq, Hugging Face y OpenRouter con modelos `:free` (con capa gratuita) y Mistral u Ollama. Basta con poner la clave en `.env`; el que no tenga clave se salta.

**Rotación de claves:** cualquier `*_API_KEY` admite varias claves separadas por comas (`HUGGINGFACE_API_KEY=hf_aaa,hf_bbb`). Se usan por turnos y, si una llega al límite de tasa, el siguiente reintento usa otra, de modo que las cuotas gratuitas se suman. Recuerda incluir el proveedor en `AI_FALLBACK_ORDER`.

## Caso 1: API compatible con OpenAI (lo habitual)

Groq, OpenRouter, Mistral, Together, DeepSeek, Ollama, LM Studio o vLLM exponen `/chat/completions`. **No hace falta código**, solo una entrada en `config/ai.php`, dentro de `providers`:

```php
'deepseek' => [
    'driver' => 'openai',
    'api_key' => env('DEEPSEEK_API_KEY'),
    'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
    'models' => [
        'fast' => env('DEEPSEEK_MODEL_FAST', 'deepseek-chat'),
        'quality' => env('DEEPSEEK_MODEL_QUALITY', 'deepseek-chat'),
    ],
    // 'user_keys' => true,   // si los usuarios pueden traer su propia clave
],
```

Después:

1. Añade la clave a `AI_FALLBACK_ORDER` donde quieras que entre (`anthropic,deepseek,gemini,…`).
2. Añade los precios por millón de tokens a `ai.pricing` (`'deepseek-chat' => ['input' => …, 'output' => …]`). Un modelo sin precio se registra con coste 0 y no cuenta para el presupuesto.
3. Documenta las variables en `.env.example`.

`fast` se usa para las tareas baratas (extraer requisitos, resumir GitHub o un CV) y `quality` para la carta y la adaptación del CV (`ModelTier`).

## Caso 2: API propia

1. Crea `app/Modules/AI/Infrastructure/Providers/<Nombre>Provider.php` extendiendo `HttpAiProvider`. Esa clase ya resuelve el nombre, el modelo por nivel, la disponibilidad (si hay clave del servidor o del usuario), la URL base, el timeout y el manejo de errores HTTP. Este último convierte los 4xx en fallos definitivos y los 429, los 5xx y los timeouts en fallos con reintento. Solo te queda implementar `complete()`:

   ```php
   public function complete(AiCompletion $completion): AiResult
   {
       $key = $this->key($completion);

       $json = $this->post($this->url('/generate'), [
           'model' => $completion->model,
           'system' => $this->systemWithSchema($completion), // prompt + esquema JSON
           'input' => $completion->input,
           'max_tokens' => $completion->maxOutputTokens,
       ], fn (PendingRequest $request): PendingRequest => $request->withHeaders(['X-Api-Key' => $key]));

       return new AiResult(
           data: AiResult::decode((string) ($json['output'] ?? '')),
           inputTokens: (int) ($json['usage']['input'] ?? 0),
           outputTokens: (int) ($json['usage']['output'] ?? 0),
       );
   }
   ```

   Si la API admite salida estructurada nativa (*tool use*, `response_schema`), pásale `$completion->schema`, como hacen `AnthropicProvider` y `GeminiProvider`. Es más fiable que pedir JSON en el prompt.

2. Registra el driver en `AiServiceProvider::DRIVERS` (`'nombre' => NombreProvider::class`) y añade la entrada en `config/ai.php` con `'driver' => 'nombre'`, como en el caso 1.

## Tests

Copia el patrón de `tests/Feature/AI/AiGatewayTest.php`. Con `Http::fake()` y `Http::preventStrayRequests()`, comprueba que:

- la petición lleva el modelo, la clave y el esquema esperados;
- una respuesta válida se decodifica y registra los tokens;
- un 429 o un 500 pasan al siguiente proveedor;
- una respuesta que no cumple el esquema se reintenta una vez.

Ningún test debe tocar una API real.
