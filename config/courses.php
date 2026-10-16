<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Learning resources per skill
|--------------------------------------------------------------------------
|
| A curated list, keyed by the slugs of config/skills.php. Each entry is
| [title, provider, url, price]: price is "free" or "paid". Only official
| documentation, well-known free courses and the vendor's own pages are
| listed, so nothing here is invented. `php artisan courses:check` fetches
| every URL and reports the ones that no longer answer.
|
| Skills without an entry still get search links (see 'search'), so a missing
| skill is never a dead end. Free options are always listed before paid ones.
|
*/

$free = fn (string $title, string $provider, string $url): array => [$title, $provider, $url, 'free'];
$paid = fn (string $title, string $provider, string $url): array => [$title, $provider, $url, 'paid'];

return [

    'catalog' => [
        'php' => [
            $free('PHP: The Right Way', 'phptherightway.com', 'https://phptherightway.com/'),
            $free('PHP manual: language reference', 'php.net', 'https://www.php.net/manual/en/langref.php'),
            $paid('Laracasts: PHP and Laravel series', 'Laracasts', 'https://laracasts.com/'),
        ],
        'laravel' => [
            $free('Laravel Learn', 'laravel.com', 'https://laravel.com/learn'),
            $free('Laravel documentation', 'laravel.com', 'https://laravel.com/docs'),
            $paid('Laracasts', 'Laracasts', 'https://laracasts.com/'),
        ],
        'javascript' => [
            $free('The Modern JavaScript Tutorial', 'javascript.info', 'https://javascript.info/'),
            $free('Learn web development', 'MDN', 'https://developer.mozilla.org/en-US/docs/Learn_web_development'),
            $free('freeCodeCamp curriculum', 'freeCodeCamp', 'https://www.freecodecamp.org/learn'),
        ],
        'typescript' => [
            $free('TypeScript Handbook', 'typescriptlang.org', 'https://www.typescriptlang.org/docs/handbook/intro.html'),
            $free('Total TypeScript tutorials', 'Total TypeScript', 'https://www.totaltypescript.com/tutorials'),
            $paid('Total TypeScript workshops', 'Total TypeScript', 'https://www.totaltypescript.com/'),
        ],
        'python' => [
            $free('The Python Tutorial', 'python.org', 'https://docs.python.org/3/tutorial/'),
            $free('CS50\'s Introduction to Programming with Python', 'Harvard CS50', 'https://cs50.harvard.edu/python/'),
        ],
        'java' => [$free('Learn Java', 'dev.java', 'https://dev.java/learn/')],
        'kotlin' => [$free('Kotlin getting started', 'kotlinlang.org', 'https://kotlinlang.org/docs/getting-started.html')],
        'swift' => [$free('The Swift Programming Language', 'swift.org', 'https://docs.swift.org/swift-book/')],
        'csharp' => [$free('A tour of C#', 'Microsoft Learn', 'https://learn.microsoft.com/en-us/dotnet/csharp/tour-of-csharp/')],
        'cpp' => [$free('Learn C++', 'learncpp.com', 'https://www.learncpp.com/')],
        'go' => [$free('A Tour of Go', 'go.dev', 'https://go.dev/tour/')],
        'rust' => [$free('The Rust Programming Language', 'rust-lang.org', 'https://doc.rust-lang.org/book/')],
        'ruby' => [$free('Ruby documentation', 'ruby-lang.org', 'https://www.ruby-lang.org/en/documentation/')],
        'rails' => [$free('Getting Started with Rails', 'Rails Guides', 'https://guides.rubyonrails.org/getting_started.html')],
        'sql' => [$free('SQLBolt: interactive SQL lessons', 'SQLBolt', 'https://sqlbolt.com/')],
        'html' => [$free('Learn web development', 'MDN', 'https://developer.mozilla.org/en-US/docs/Learn_web_development')],
        'css' => [$free('Learn web development', 'MDN', 'https://developer.mozilla.org/en-US/docs/Learn_web_development')],
        'sass' => [$free('Sass guide', 'sass-lang.com', 'https://sass-lang.com/guide/')],
        'vue' => [
            $free('Vue tutorial', 'vuejs.org', 'https://vuejs.org/tutorial/'),
            $paid('Vue Mastery courses', 'Vue Mastery', 'https://www.vuemastery.com/courses'),
        ],
        'nuxt' => [$free('Nuxt introduction', 'nuxt.com', 'https://nuxt.com/docs/getting-started/introduction')],
        'react' => [
            $free('Learn React', 'react.dev', 'https://react.dev/learn'),
            $paid('Epic React', 'Epic React', 'https://www.epicreact.dev/'),
        ],
        'nextjs' => [$free('Learn Next.js', 'nextjs.org', 'https://nextjs.org/learn')],
        'angular' => [$free('Angular tutorials', 'angular.dev', 'https://angular.dev/tutorials')],
        'svelte' => [$free('Svelte tutorial', 'svelte.dev', 'https://svelte.dev/tutorial')],
        'tailwindcss' => [
            $free('Tailwind CSS documentation', 'tailwindcss.com', 'https://tailwindcss.com/docs/installation'),
            $paid('Refactoring UI', 'Refactoring UI', 'https://www.refactoringui.com/'),
        ],
        'inertia' => [$free('Inertia.js documentation', 'inertiajs.com', 'https://inertiajs.com/')],
        'livewire' => [$free('Livewire quickstart', 'livewire.laravel.com', 'https://livewire.laravel.com/docs/quickstart')],
        'vite' => [$free('Vite guide', 'vite.dev', 'https://vite.dev/guide/')],
        'symfony' => [$free('Symfony Quick Tour', 'symfony.com', 'https://symfony.com/doc/current/quick_tour/index.html')],
        'wordpress' => [$free('Learn WordPress', 'learn.wordpress.org', 'https://learn.wordpress.org/')],
        'nodejs' => [$free('Learn Node.js', 'nodejs.org', 'https://nodejs.org/en/learn')],
        'django' => [$free('Django tutorial', 'djangoproject.com', 'https://docs.djangoproject.com/en/stable/intro/tutorial01/')],
        'flask' => [$free('Flask tutorial', 'flask.palletsprojects.com', 'https://flask.palletsprojects.com/en/stable/tutorial/')],
        'fastapi' => [$free('FastAPI tutorial', 'fastapi.tiangolo.com', 'https://fastapi.tiangolo.com/tutorial/')],
        'spring' => [$free('Spring guides', 'spring.io', 'https://spring.io/guides')],
        'graphql' => [$free('Introduction to GraphQL', 'graphql.org', 'https://graphql.org/learn/')],
        'react-native' => [$free('React Native: getting started', 'reactnative.dev', 'https://reactnative.dev/docs/getting-started')],
        'flutter' => [$free('Learn Flutter', 'flutter.dev', 'https://docs.flutter.dev/get-started/learn-flutter')],
        'android' => [$free('Android courses', 'developer.android.com', 'https://developer.android.com/courses')],
        'docker' => [
            $free('Docker get started', 'docker.com', 'https://docs.docker.com/get-started/'),
            $paid('KodeKloud courses', 'KodeKloud', 'https://kodekloud.com/courses'),
        ],
        'kubernetes' => [
            $free('Kubernetes tutorials', 'kubernetes.io', 'https://kubernetes.io/docs/tutorials/'),
            $paid('KodeKloud courses', 'KodeKloud', 'https://kodekloud.com/courses'),
        ],
        'terraform' => [$free('Terraform tutorials', 'HashiCorp Developer', 'https://developer.hashicorp.com/terraform/tutorials')],
        'ansible' => [$free('Ansible getting started', 'ansible.com', 'https://docs.ansible.com/ansible/latest/getting_started/index.html')],
        'aws' => [
            $free('AWS Skill Builder: free digital training', 'AWS', 'https://explore.skillbuilder.aws/learn'),
            $paid('AWS courses by Adrian Cantrill', 'learn.cantrill.io', 'https://learn.cantrill.io/'),
        ],
        'gcp' => [$free('Google Cloud Skills Boost', 'Google Cloud', 'https://www.cloudskillsboost.google/')],
        'azure' => [$free('Azure training', 'Microsoft Learn', 'https://learn.microsoft.com/en-us/training/azure/')],
        'linux' => [$free('Linux Journey', 'linuxjourney.com', 'https://linuxjourney.com/')],
        'git' => [$free('Pro Git book', 'git-scm.com', 'https://git-scm.com/book/en/v2')],
        'github-actions' => [$free('GitHub Actions quickstart', 'GitHub Docs', 'https://docs.github.com/en/actions/quickstart')],
        'postgresql' => [$free('PostgreSQL tutorial', 'postgresql.org', 'https://www.postgresql.org/docs/current/tutorial.html')],
        'mysql' => [$free('MySQL tutorial', 'mysql.com', 'https://dev.mysql.com/doc/refman/8.0/en/tutorial.html')],
        'mongodb' => [$free('MongoDB University', 'MongoDB', 'https://learn.mongodb.com/')],
        'redis' => [$free('Redis University', 'Redis', 'https://university.redis.io/')],
        'kafka' => [$free('Confluent developer courses', 'Confluent', 'https://developer.confluent.io/courses/')],
        'pandas' => [$free('Getting started with pandas', 'pandas.pydata.org', 'https://pandas.pydata.org/docs/getting_started/index.html')],
        'numpy' => [$free('Learn NumPy', 'numpy.org', 'https://numpy.org/learn/')],
        'scikit-learn' => [$free('scikit-learn tutorials', 'scikit-learn.org', 'https://scikit-learn.org/stable/getting_started.html')],
        'pytorch' => [$free('PyTorch tutorials', 'pytorch.org', 'https://pytorch.org/tutorials/')],
        'tensorflow' => [$free('TensorFlow tutorials', 'tensorflow.org', 'https://www.tensorflow.org/tutorials')],
        'llm' => [$free('Hugging Face courses', 'Hugging Face', 'https://huggingface.co/learn')],
        'pest' => [$free('Pest documentation', 'pestphp.com', 'https://pestphp.com/docs/installation')],
        'playwright' => [$free('Playwright introduction', 'playwright.dev', 'https://playwright.dev/docs/intro')],
        'cypress' => [$free('Cypress Learn', 'cypress.io', 'https://learn.cypress.io/')],
        'jest' => [$free('Jest getting started', 'jestjs.io', 'https://jestjs.io/docs/getting-started')],
        'vitest' => [$free('Vitest guide', 'vitest.dev', 'https://vitest.dev/guide/')],
        'accessibility' => [$free('Learn Accessibility', 'web.dev', 'https://web.dev/learn/accessibility')],
        'seo' => [$free('SEO starter guide', 'Google Search Central', 'https://developers.google.com/search/docs/fundamentals/seo-starter-guide')],
        'security' => [
            $free('Web Security Academy', 'PortSwigger', 'https://portswigger.net/web-security'),
            $free('OWASP Top Ten', 'OWASP', 'https://owasp.org/www-project-top-ten/'),
        ],
        'agile' => [$free('The Scrum Guide', 'scrumguides.org', 'https://scrumguides.org/')],
    ],

    /*
     * Search links for any skill: free places first, then paid ones. {query}
     * is the URL-encoded skill name.
     */
    'search' => [
        ['title' => 'Free video courses', 'provider' => 'YouTube', 'url' => 'https://www.youtube.com/results?search_query={query}+full+course', 'price' => 'free'],
        ['title' => 'Free tutorials and articles', 'provider' => 'freeCodeCamp', 'url' => 'https://www.freecodecamp.org/news/search/?query={query}', 'price' => 'free'],
        ['title' => 'Free university courses', 'provider' => 'MIT OpenCourseWare', 'url' => 'https://ocw.mit.edu/search/?q={query}', 'price' => 'free'],
        ['title' => 'Courses', 'provider' => 'Coursera', 'url' => 'https://www.coursera.org/search?query={query}', 'price' => 'paid'],
        ['title' => 'Courses', 'provider' => 'Udemy', 'url' => 'https://www.udemy.com/courses/search/?q={query}', 'price' => 'paid'],
    ],
];
