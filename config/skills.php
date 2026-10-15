<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Skill catalog
|--------------------------------------------------------------------------
|
| Canonical skills with their synonyms, so "Vue.js", "VueJS" and "Vue 3" are
| one skill everywhere (questionnaire, CV, GitHub, LinkedIn, job matching).
| Skills outside the catalog are still accepted; they are just matched by
| their slug.
|
| - label:   display name
| - type:    language | framework | database | cloud | tool | practice
| - aliases: alternative spellings (case, dots and spaces are ignored)
| - detect:  terms searched for in free text (CVs, LinkedIn positions);
|            defaults to label + aliases, [] disables text detection for
|            words that are ambiguous in prose ("Go", "R", "Express")
| - roles:   job roles the skill points to, used to suggest a search profile
|
*/

return [

    // How much each source is trusted. Merged confidence = 1 - Π(1 - weight).
    'confidence' => [
        'declared' => 0.5,
        'cv' => 0.7,
        'linkedin' => 0.75,
        'github' => 0.9,
    ],

    'catalog' => [
        // Languages
        'php' => ['label' => 'PHP', 'type' => 'language', 'roles' => ['backend']],
        'javascript' => ['label' => 'JavaScript', 'type' => 'language', 'aliases' => ['js', 'ecmascript', 'es6', 'es2015'], 'roles' => ['frontend']],
        'typescript' => ['label' => 'TypeScript', 'type' => 'language', 'aliases' => ['ts'], 'roles' => ['frontend']],
        'python' => ['label' => 'Python', 'type' => 'language', 'aliases' => ['python3', 'py'], 'roles' => ['backend']],
        'java' => ['label' => 'Java', 'type' => 'language', 'roles' => ['backend']],
        'kotlin' => ['label' => 'Kotlin', 'type' => 'language', 'roles' => ['mobile', 'backend']],
        'swift' => ['label' => 'Swift', 'type' => 'language', 'roles' => ['mobile']],
        'objective-c' => ['label' => 'Objective-C', 'type' => 'language', 'aliases' => ['objc', 'objective c'], 'roles' => ['mobile']],
        'csharp' => ['label' => 'C#', 'type' => 'language', 'aliases' => ['c sharp', 'c-sharp'], 'roles' => ['backend']],
        'cpp' => ['label' => 'C++', 'type' => 'language', 'aliases' => ['cplusplus', 'c plus plus']],
        'c' => ['label' => 'C', 'type' => 'language', 'detect' => []],
        'go' => ['label' => 'Go', 'type' => 'language', 'aliases' => ['golang'], 'detect' => ['golang'], 'roles' => ['backend']],
        'rust' => ['label' => 'Rust', 'type' => 'language', 'aliases' => ['rustlang'], 'roles' => ['backend']],
        'ruby' => ['label' => 'Ruby', 'type' => 'language', 'roles' => ['backend']],
        'scala' => ['label' => 'Scala', 'type' => 'language', 'roles' => ['backend', 'data_engineer']],
        'elixir' => ['label' => 'Elixir', 'type' => 'language', 'roles' => ['backend']],
        'erlang' => ['label' => 'Erlang', 'type' => 'language', 'roles' => ['backend']],
        'clojure' => ['label' => 'Clojure', 'type' => 'language', 'roles' => ['backend']],
        'haskell' => ['label' => 'Haskell', 'type' => 'language'],
        'dart' => ['label' => 'Dart', 'type' => 'language', 'roles' => ['mobile']],
        'r' => ['label' => 'R', 'type' => 'language', 'aliases' => ['rlang', 'r language'], 'detect' => ['rstudio', 'tidyverse'], 'roles' => ['data_scientist', 'data_analyst']],
        'perl' => ['label' => 'Perl', 'type' => 'language'],
        'lua' => ['label' => 'Lua', 'type' => 'language'],
        'sql' => ['label' => 'SQL', 'type' => 'language', 'aliases' => ['tsql', 't-sql', 'plsql', 'pl/sql'], 'roles' => ['data_analyst', 'data_engineer', 'backend']],
        'bash' => ['label' => 'Bash', 'type' => 'language', 'aliases' => ['shell', 'shell scripting', 'sh', 'zsh'], 'detect' => ['bash', 'shell scripting'], 'roles' => ['devops']],
        'powershell' => ['label' => 'PowerShell', 'type' => 'language', 'roles' => ['devops']],
        'html' => ['label' => 'HTML', 'type' => 'language', 'aliases' => ['html5'], 'roles' => ['frontend']],
        'css' => ['label' => 'CSS', 'type' => 'language', 'aliases' => ['css3'], 'roles' => ['frontend']],
        'sass' => ['label' => 'Sass', 'type' => 'language', 'aliases' => ['scss'], 'roles' => ['frontend']],
        'solidity' => ['label' => 'Solidity', 'type' => 'language', 'roles' => ['backend']],

        // Frontend frameworks
        'vue' => ['label' => 'Vue', 'type' => 'framework', 'aliases' => ['vuejs', 'vue.js', 'vue3', 'vue 3', 'vue2'], 'roles' => ['frontend']],
        'nuxt' => ['label' => 'Nuxt', 'type' => 'framework', 'aliases' => ['nuxtjs', 'nuxt.js'], 'roles' => ['frontend']],
        'react' => ['label' => 'React', 'type' => 'framework', 'aliases' => ['reactjs', 'react.js'], 'roles' => ['frontend']],
        'nextjs' => ['label' => 'Next.js', 'type' => 'framework', 'aliases' => ['next', 'next.js', 'nextjs'], 'detect' => ['next.js', 'nextjs'], 'roles' => ['frontend']],
        'angular' => ['label' => 'Angular', 'type' => 'framework', 'aliases' => ['angular2', 'angularjs', 'angular.js'], 'roles' => ['frontend']],
        'svelte' => ['label' => 'Svelte', 'type' => 'framework', 'aliases' => ['sveltekit'], 'roles' => ['frontend']],
        'solidjs' => ['label' => 'SolidJS', 'type' => 'framework', 'aliases' => ['solid.js'], 'roles' => ['frontend']],
        'redux' => ['label' => 'Redux', 'type' => 'framework', 'roles' => ['frontend']],
        'pinia' => ['label' => 'Pinia', 'type' => 'framework', 'roles' => ['frontend']],
        'tailwindcss' => ['label' => 'Tailwind CSS', 'type' => 'framework', 'aliases' => ['tailwind'], 'roles' => ['frontend']],
        'bootstrap' => ['label' => 'Bootstrap', 'type' => 'framework', 'roles' => ['frontend']],
        'jquery' => ['label' => 'jQuery', 'type' => 'framework', 'roles' => ['frontend']],
        'inertia' => ['label' => 'Inertia.js', 'type' => 'framework', 'aliases' => ['inertiajs', 'inertia.js'], 'roles' => ['fullstack']],
        'livewire' => ['label' => 'Livewire', 'type' => 'framework', 'roles' => ['fullstack']],
        'vite' => ['label' => 'Vite', 'type' => 'tool', 'aliases' => ['vitejs'], 'roles' => ['frontend']],
        'webpack' => ['label' => 'webpack', 'type' => 'tool', 'roles' => ['frontend']],
        'threejs' => ['label' => 'Three.js', 'type' => 'framework', 'aliases' => ['three', 'three.js'], 'detect' => ['three.js', 'threejs'], 'roles' => ['frontend']],
        'd3' => ['label' => 'D3.js', 'type' => 'framework', 'aliases' => ['d3js', 'd3.js'], 'roles' => ['frontend', 'data_analyst']],

        // Mobile
        'react-native' => ['label' => 'React Native', 'type' => 'framework', 'aliases' => ['reactnative'], 'roles' => ['mobile']],
        'flutter' => ['label' => 'Flutter', 'type' => 'framework', 'roles' => ['mobile']],
        'swiftui' => ['label' => 'SwiftUI', 'type' => 'framework', 'roles' => ['mobile']],
        'jetpack-compose' => ['label' => 'Jetpack Compose', 'type' => 'framework', 'aliases' => ['compose'], 'detect' => ['jetpack compose'], 'roles' => ['mobile']],
        'android' => ['label' => 'Android', 'type' => 'framework', 'roles' => ['mobile']],
        'ios' => ['label' => 'iOS', 'type' => 'framework', 'roles' => ['mobile']],
        'ionic' => ['label' => 'Ionic', 'type' => 'framework', 'roles' => ['mobile']],
        'expo' => ['label' => 'Expo', 'type' => 'framework', 'detect' => [], 'roles' => ['mobile']],

        // Backend frameworks
        'laravel' => ['label' => 'Laravel', 'type' => 'framework', 'roles' => ['backend']],
        'symfony' => ['label' => 'Symfony', 'type' => 'framework', 'roles' => ['backend']],
        'wordpress' => ['label' => 'WordPress', 'type' => 'framework', 'aliases' => ['wp'], 'roles' => ['backend']],
        'drupal' => ['label' => 'Drupal', 'type' => 'framework', 'roles' => ['backend']],
        'magento' => ['label' => 'Magento', 'type' => 'framework', 'roles' => ['backend']],
        'nodejs' => ['label' => 'Node.js', 'type' => 'framework', 'aliases' => ['node', 'node.js', 'nodejs'], 'detect' => ['node.js', 'nodejs'], 'roles' => ['backend']],
        'express' => ['label' => 'Express', 'type' => 'framework', 'aliases' => ['expressjs', 'express.js'], 'detect' => ['express.js', 'expressjs'], 'roles' => ['backend']],
        'nestjs' => ['label' => 'NestJS', 'type' => 'framework', 'aliases' => ['nest.js', 'nest'], 'detect' => ['nestjs', 'nest.js'], 'roles' => ['backend']],
        'deno' => ['label' => 'Deno', 'type' => 'framework', 'roles' => ['backend']],
        'django' => ['label' => 'Django', 'type' => 'framework', 'roles' => ['backend']],
        'flask' => ['label' => 'Flask', 'type' => 'framework', 'roles' => ['backend']],
        'fastapi' => ['label' => 'FastAPI', 'type' => 'framework', 'roles' => ['backend']],
        'spring' => ['label' => 'Spring', 'type' => 'framework', 'aliases' => ['spring boot', 'springboot', 'spring framework'], 'detect' => ['spring boot', 'springboot', 'spring framework'], 'roles' => ['backend']],
        'dotnet' => ['label' => '.NET', 'type' => 'framework', 'aliases' => ['.net', 'net core', '.net core', 'asp.net', 'aspnet', 'dotnet core'], 'roles' => ['backend']],
        'rails' => ['label' => 'Ruby on Rails', 'type' => 'framework', 'aliases' => ['ruby on rails', 'ror', 'rails'], 'roles' => ['backend']],
        'phoenix' => ['label' => 'Phoenix', 'type' => 'framework', 'detect' => [], 'roles' => ['backend']],
        'gin' => ['label' => 'Gin', 'type' => 'framework', 'detect' => [], 'roles' => ['backend']],
        'graphql' => ['label' => 'GraphQL', 'type' => 'practice', 'roles' => ['backend', 'frontend']],
        'rest-api' => ['label' => 'REST APIs', 'type' => 'practice', 'aliases' => ['rest', 'restful', 'rest api', 'restful api'], 'detect' => ['rest api', 'restful', 'rest apis'], 'roles' => ['backend']],
        'grpc' => ['label' => 'gRPC', 'type' => 'practice', 'roles' => ['backend']],
        'microservices' => ['label' => 'Microservices', 'type' => 'practice', 'aliases' => ['microservice', 'micro-services'], 'roles' => ['backend']],

        // Data & ML
        'pandas' => ['label' => 'pandas', 'type' => 'framework', 'roles' => ['data_scientist', 'data_analyst']],
        'numpy' => ['label' => 'NumPy', 'type' => 'framework', 'roles' => ['data_scientist']],
        'scikit-learn' => ['label' => 'scikit-learn', 'type' => 'framework', 'aliases' => ['sklearn'], 'roles' => ['machine_learning', 'data_scientist']],
        'tensorflow' => ['label' => 'TensorFlow', 'type' => 'framework', 'roles' => ['machine_learning']],
        'pytorch' => ['label' => 'PyTorch', 'type' => 'framework', 'aliases' => ['torch'], 'roles' => ['machine_learning']],
        'keras' => ['label' => 'Keras', 'type' => 'framework', 'roles' => ['machine_learning']],
        'llm' => ['label' => 'LLMs', 'type' => 'practice', 'aliases' => ['large language models', 'llms', 'genai', 'generative ai'], 'roles' => ['machine_learning']],
        'langchain' => ['label' => 'LangChain', 'type' => 'framework', 'roles' => ['machine_learning']],
        'spark' => ['label' => 'Apache Spark', 'type' => 'framework', 'aliases' => ['spark', 'pyspark', 'apache spark'], 'roles' => ['data_engineer']],
        'airflow' => ['label' => 'Airflow', 'type' => 'tool', 'aliases' => ['apache airflow'], 'roles' => ['data_engineer']],
        'dbt' => ['label' => 'dbt', 'type' => 'tool', 'roles' => ['data_engineer', 'data_analyst']],
        'kafka' => ['label' => 'Kafka', 'type' => 'tool', 'aliases' => ['apache kafka'], 'roles' => ['data_engineer', 'backend']],
        'tableau' => ['label' => 'Tableau', 'type' => 'tool', 'roles' => ['data_analyst']],
        'power-bi' => ['label' => 'Power BI', 'type' => 'tool', 'aliases' => ['powerbi'], 'roles' => ['data_analyst']],
        'excel' => ['label' => 'Excel', 'type' => 'tool', 'aliases' => ['microsoft excel', 'ms excel'], 'roles' => ['data_analyst', 'finance']],
        'jupyter' => ['label' => 'Jupyter', 'type' => 'tool', 'aliases' => ['jupyter notebook', 'jupyterlab'], 'roles' => ['data_scientist']],

        // Databases
        'postgresql' => ['label' => 'PostgreSQL', 'type' => 'database', 'aliases' => ['postgres', 'psql', 'pgsql'], 'roles' => ['backend']],
        'mysql' => ['label' => 'MySQL', 'type' => 'database', 'aliases' => ['mariadb'], 'roles' => ['backend']],
        'sqlite' => ['label' => 'SQLite', 'type' => 'database'],
        'sql-server' => ['label' => 'SQL Server', 'type' => 'database', 'aliases' => ['mssql', 'microsoft sql server', 'ms sql'], 'roles' => ['backend']],
        'oracle' => ['label' => 'Oracle Database', 'type' => 'database', 'aliases' => ['oracle db', 'oracle'], 'roles' => ['backend']],
        'mongodb' => ['label' => 'MongoDB', 'type' => 'database', 'aliases' => ['mongo'], 'roles' => ['backend']],
        'redis' => ['label' => 'Redis', 'type' => 'database', 'roles' => ['backend']],
        'elasticsearch' => ['label' => 'Elasticsearch', 'type' => 'database', 'aliases' => ['elastic search', 'opensearch'], 'roles' => ['backend']],
        'dynamodb' => ['label' => 'DynamoDB', 'type' => 'database', 'roles' => ['backend']],
        'cassandra' => ['label' => 'Cassandra', 'type' => 'database', 'roles' => ['data_engineer']],
        'snowflake' => ['label' => 'Snowflake', 'type' => 'database', 'roles' => ['data_engineer']],
        'bigquery' => ['label' => 'BigQuery', 'type' => 'database', 'aliases' => ['big query'], 'roles' => ['data_engineer', 'data_analyst']],
        'firebase' => ['label' => 'Firebase', 'type' => 'cloud', 'roles' => ['mobile', 'frontend']],
        'supabase' => ['label' => 'Supabase', 'type' => 'cloud', 'roles' => ['fullstack']],

        // Cloud & DevOps
        'aws' => ['label' => 'AWS', 'type' => 'cloud', 'aliases' => ['amazon web services'], 'roles' => ['devops']],
        'gcp' => ['label' => 'Google Cloud', 'type' => 'cloud', 'aliases' => ['google cloud platform', 'google cloud'], 'roles' => ['devops']],
        'azure' => ['label' => 'Azure', 'type' => 'cloud', 'aliases' => ['microsoft azure'], 'roles' => ['devops']],
        'docker' => ['label' => 'Docker', 'type' => 'tool', 'aliases' => ['docker compose', 'docker-compose', 'dockerfile'], 'roles' => ['devops']],
        'kubernetes' => ['label' => 'Kubernetes', 'type' => 'tool', 'aliases' => ['k8s'], 'roles' => ['devops']],
        'helm' => ['label' => 'Helm', 'type' => 'tool', 'detect' => [], 'roles' => ['devops']],
        'terraform' => ['label' => 'Terraform', 'type' => 'tool', 'aliases' => ['hcl'], 'roles' => ['devops']],
        'ansible' => ['label' => 'Ansible', 'type' => 'tool', 'roles' => ['devops']],
        'linux' => ['label' => 'Linux', 'type' => 'tool', 'roles' => ['devops']],
        'nginx' => ['label' => 'Nginx', 'type' => 'tool', 'roles' => ['devops']],
        'ci-cd' => ['label' => 'CI/CD', 'type' => 'practice', 'aliases' => ['ci/cd', 'cicd', 'continuous integration', 'continuous delivery'], 'roles' => ['devops']],
        'github-actions' => ['label' => 'GitHub Actions', 'type' => 'tool', 'roles' => ['devops']],
        'gitlab-ci' => ['label' => 'GitLab CI', 'type' => 'tool', 'aliases' => ['gitlab ci/cd'], 'roles' => ['devops']],
        'jenkins' => ['label' => 'Jenkins', 'type' => 'tool', 'roles' => ['devops']],
        'prometheus' => ['label' => 'Prometheus', 'type' => 'tool', 'roles' => ['devops']],
        'grafana' => ['label' => 'Grafana', 'type' => 'tool', 'roles' => ['devops']],
        'serverless' => ['label' => 'Serverless', 'type' => 'practice', 'aliases' => ['aws lambda', 'lambda'], 'detect' => ['serverless', 'aws lambda'], 'roles' => ['devops', 'backend']],
        'rabbitmq' => ['label' => 'RabbitMQ', 'type' => 'tool', 'roles' => ['backend']],

        // Testing & quality
        'phpunit' => ['label' => 'PHPUnit', 'type' => 'tool', 'roles' => ['backend']],
        'pest' => ['label' => 'Pest', 'type' => 'tool', 'aliases' => ['pestphp'], 'detect' => ['pestphp'], 'roles' => ['backend']],
        'jest' => ['label' => 'Jest', 'type' => 'tool', 'roles' => ['frontend']],
        'vitest' => ['label' => 'Vitest', 'type' => 'tool', 'roles' => ['frontend']],
        'cypress' => ['label' => 'Cypress', 'type' => 'tool', 'roles' => ['qa', 'frontend']],
        'playwright' => ['label' => 'Playwright', 'type' => 'tool', 'roles' => ['qa', 'frontend']],
        'selenium' => ['label' => 'Selenium', 'type' => 'tool', 'roles' => ['qa']],
        'pytest' => ['label' => 'pytest', 'type' => 'tool', 'roles' => ['backend']],
        'tdd' => ['label' => 'TDD', 'type' => 'practice', 'aliases' => ['test driven development', 'test-driven development'], 'roles' => ['backend']],

        // Tools & practices
        'git' => ['label' => 'Git', 'type' => 'tool'],
        'agile' => ['label' => 'Agile', 'type' => 'practice', 'aliases' => ['scrum', 'kanban'], 'roles' => ['project_manager', 'product_manager']],
        'jira' => ['label' => 'Jira', 'type' => 'tool', 'roles' => ['project_manager']],
        'figma' => ['label' => 'Figma', 'type' => 'tool', 'roles' => ['designer']],
        'sketch' => ['label' => 'Sketch', 'type' => 'tool', 'detect' => [], 'roles' => ['designer']],
        'adobe-xd' => ['label' => 'Adobe XD', 'type' => 'tool', 'roles' => ['designer']],
        'photoshop' => ['label' => 'Photoshop', 'type' => 'tool', 'aliases' => ['adobe photoshop'], 'roles' => ['designer']],
        'ux-research' => ['label' => 'UX research', 'type' => 'practice', 'aliases' => ['user research'], 'roles' => ['designer']],
        'seo' => ['label' => 'SEO', 'type' => 'practice', 'roles' => ['marketing']],
        'google-analytics' => ['label' => 'Google Analytics', 'type' => 'tool', 'aliases' => ['ga4'], 'roles' => ['marketing']],
        'hubspot' => ['label' => 'HubSpot', 'type' => 'tool', 'roles' => ['marketing', 'sales']],
        'salesforce' => ['label' => 'Salesforce', 'type' => 'tool', 'roles' => ['sales']],
        'zendesk' => ['label' => 'Zendesk', 'type' => 'tool', 'roles' => ['customer_support']],
        'security' => ['label' => 'Application security', 'type' => 'practice', 'aliases' => ['appsec', 'owasp', 'cybersecurity'], 'roles' => ['security']],
        'accessibility' => ['label' => 'Accessibility', 'type' => 'practice', 'aliases' => ['a11y', 'wcag'], 'roles' => ['frontend', 'designer']],
    ],

    /*
    | Package => skill, per ecosystem, for GitHub manifests. A trailing "*"
    | matches a package prefix ("symfony/*").
    */
    'packages' => [
        'composer' => [
            'laravel/framework' => 'laravel', 'laravel/*' => 'laravel', 'illuminate/*' => 'laravel',
            'symfony/framework-bundle' => 'symfony', 'symfony/symfony' => 'symfony',
            'livewire/livewire' => 'livewire', 'inertiajs/inertia-laravel' => 'inertia',
            'phpunit/phpunit' => 'phpunit', 'pestphp/pest' => 'pest',
            'drupal/core' => 'drupal', 'magento/*' => 'magento', 'wpackagist-plugin/*' => 'wordpress',
            'predis/predis' => 'redis', 'elasticsearch/elasticsearch' => 'elasticsearch',
            'aws/aws-sdk-php' => 'aws', 'webonyx/graphql-php' => 'graphql', 'nuwave/lighthouse' => 'graphql',
            'php-amqplib/php-amqplib' => 'rabbitmq', 'doctrine/mongodb-odm' => 'mongodb', 'mongodb/mongodb' => 'mongodb',
        ],
        'npm' => [
            'vue' => 'vue', 'nuxt' => 'nuxt', 'pinia' => 'pinia', 'react' => 'react', 'next' => 'nextjs',
            '@angular/core' => 'angular', 'svelte' => 'svelte', '@sveltejs/kit' => 'svelte', 'solid-js' => 'solidjs',
            'redux' => 'redux', '@reduxjs/toolkit' => 'redux', 'tailwindcss' => 'tailwindcss', 'bootstrap' => 'bootstrap',
            'jquery' => 'jquery', '@inertiajs/*' => 'inertia', 'vite' => 'vite', 'webpack' => 'webpack',
            'three' => 'threejs', 'd3' => 'd3', 'react-native' => 'react-native', 'expo' => 'expo', '@ionic/*' => 'ionic',
            'typescript' => 'typescript', 'express' => 'express', '@nestjs/core' => 'nestjs', 'graphql' => 'graphql',
            '@apollo/*' => 'graphql', 'jest' => 'jest', 'vitest' => 'vitest', 'cypress' => 'cypress',
            '@playwright/test' => 'playwright', 'playwright' => 'playwright', 'selenium-webdriver' => 'selenium',
            'mongoose' => 'mongodb', 'mongodb' => 'mongodb', 'pg' => 'postgresql', 'mysql2' => 'mysql', 'mysql' => 'mysql',
            'redis' => 'redis', 'ioredis' => 'redis', 'firebase' => 'firebase', '@supabase/*' => 'supabase',
            'kafkajs' => 'kafka', 'amqplib' => 'rabbitmq', 'aws-sdk' => 'aws', '@aws-sdk/*' => 'aws', 'sass' => 'sass',
            'langchain' => 'langchain', '@langchain/*' => 'langchain', 'serverless' => 'serverless',
        ],
        'pypi' => [
            'django' => 'django', 'flask' => 'flask', 'fastapi' => 'fastapi', 'pandas' => 'pandas', 'numpy' => 'numpy',
            'scikit-learn' => 'scikit-learn', 'sklearn' => 'scikit-learn', 'tensorflow' => 'tensorflow', 'torch' => 'pytorch',
            'keras' => 'keras', 'langchain' => 'langchain', 'pyspark' => 'spark', 'apache-airflow' => 'airflow',
            'dbt-core' => 'dbt', 'pytest' => 'pytest', 'psycopg2' => 'postgresql', 'psycopg2-binary' => 'postgresql',
            'psycopg' => 'postgresql', 'pymongo' => 'mongodb', 'redis' => 'redis', 'boto3' => 'aws', 'kafka-python' => 'kafka',
            'selenium' => 'selenium', 'playwright' => 'playwright', 'jupyter' => 'jupyter', 'graphene' => 'graphql',
        ],
        'rubygems' => [
            'rails' => 'rails', 'rspec' => 'tdd', 'pg' => 'postgresql', 'mysql2' => 'mysql', 'redis' => 'redis', 'sidekiq' => 'redis',
        ],
        'go' => [
            'github.com/gin-gonic/gin' => 'gin', 'google.golang.org/grpc' => 'grpc', 'github.com/lib/pq' => 'postgresql',
            'github.com/jackc/pgx*' => 'postgresql', 'github.com/redis/go-redis*' => 'redis', 'github.com/aws/aws-sdk-go*' => 'aws',
            'k8s.io/*' => 'kubernetes', 'go.mongodb.org/mongo-driver' => 'mongodb',
        ],
        'cargo' => [
            'tokio' => 'rust', 'actix-web' => 'rust', 'tonic' => 'grpc', 'sqlx' => 'sql', 'redis' => 'redis',
        ],
        'maven' => [
            'org.springframework.boot' => 'spring', 'org.springframework' => 'spring', 'junit' => 'tdd', 'org.junit*' => 'tdd',
            'org.postgresql' => 'postgresql', 'mysql' => 'mysql', 'org.apache.kafka' => 'kafka', 'software.amazon.awssdk' => 'aws',
        ],
    ],

    // GitHub languages that say nothing about a candidate's skills.
    'ignored_languages' => [
        'Makefile', 'Procfile', 'Batchfile', 'CMake', 'Roff', 'Text', 'Markdown', 'Jinja', 'Smarty',
        'Mustache', 'Handlebars', 'Twig', 'Blade', 'EJS', 'Pug', 'Less', 'Stylus', 'Nix', 'Starlark', 'M4', 'Vim Script',
    ],

];
