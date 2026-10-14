<?php

declare(strict_types=1);

use App\Modules\Profiles\Infrastructure\GitHub\ManifestParser;

it('lists dependencies of each manifest format', function (string $file, string $contents, array $expected): void {
    expect((new ManifestParser)->packages($file, $contents))->toContain(...$expected);
})->with([
    'composer' => ['composer.json', '{"require":{"php":"^8.4","laravel/framework":"^13.0"},"require-dev":{"pestphp/pest":"^5"}}', ['laravel/framework', 'pestphp/pest']],
    'npm' => ['package.json', '{"dependencies":{"vue":"^3"},"devDependencies":{"@inertiajs/vue3":"^3","Vite":"^7"}}', ['vue', '@inertiajs/vue3', 'vite']],
    'requirements' => ['requirements.txt', "# deps\nDjango==5.0\nscikit_learn>=1.4 ; python_version > '3.9'\n-r dev.txt\npandas", ['django', 'scikit-learn', 'pandas']],
    'pyproject pep 621' => ['pyproject.toml', "[project]\ndependencies = [\n  \"fastapi>=0.110\",\n  \"uvicorn[standard]\",\n]", ['fastapi', 'uvicorn']],
    'pyproject poetry' => ['pyproject.toml', "[tool.poetry.dependencies]\npython = \"^3.12\"\nflask = \"^3\"\n\n[tool.poetry.group.dev.dependencies]\npytest = \"*\"", ['flask', 'pytest']],
    'pipfile' => ['Pipfile', "[packages]\nrequests = \"*\"\n[dev-packages]\npytest = \"*\"", ['requests', 'pytest']],
    'gemfile' => ['Gemfile', "source 'https://rubygems.org'\ngem 'rails', '~> 7.1'\ngem \"pg\"", ['rails', 'pg']],
    'go.mod' => ['go.mod', "module x\n\nrequire github.com/lib/pq v1.10.9\n\nrequire (\n\tgithub.com/gin-gonic/gin v1.9.1\n\tk8s.io/client-go v0.29.0\n)", ['github.com/lib/pq', 'github.com/gin-gonic/gin', 'k8s.io/client-go']],
    'cargo' => ['Cargo.toml', "[package]\nname = \"x\"\n\n[dependencies]\ntokio = { version = \"1\" }\nserde = \"1\"\n\n[dev-dependencies]\ntonic = \"0.11\"", ['tokio', 'serde', 'tonic']],
    'maven' => ['pom.xml', '<project><parent><groupId>org.springframework.boot</groupId></parent><dependencies><dependency><groupId>org.postgresql</groupId><artifactId>postgresql</artifactId></dependency></dependencies></project>', ['org.springframework.boot', 'org.postgresql']],
    'gradle' => ['build.gradle.kts', "plugins { id(\"org.springframework.boot\") version \"3.2.0\" }\ndependencies { implementation(\"org.apache.kafka:kafka-clients:3.6.0\") }", ['org.springframework.boot', 'org.apache.kafka']],
]);

it('returns nothing for broken or unknown manifests', function (string $file, string $contents): void {
    expect((new ManifestParser)->packages($file, $contents))->toBe([]);
})->with([
    ['composer.json', '{not json'],
    ['package.json', '"just a string"'],
    ['README.md', '# hello'],
]);

it('does not count package metadata keys as dependencies', function (): void {
    $packages = (new ManifestParser)->packages('Cargo.toml', "[package]\nname = \"tokio-app\"\nversion = \"0.1.0\"\n");

    expect($packages)->toBe([]);
});
