param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('run', 'test', 'phpstan', 'phpfixer')]
    [string]$Target
)

$ErrorActionPreference = 'Stop'

function Invoke-DockerCompose {
    param([string[]]$Args)
    & docker compose @Args
}

switch ($Target) {
    'run' {
        Invoke-DockerCompose @('up', '--build')
    }
    'test' {
        Invoke-DockerCompose @('run', '--rm', 'app', './vendor/bin/phpunit')
    }
    'phpstan' {
        Invoke-DockerCompose @('run', '--rm', 'app', './vendor/bin/phpstan', 'analyse', '-c', 'phpstan.neon')
    }
    'phpfixer' {
        Invoke-DockerCompose @('run', '--rm', 'app', './vendor/bin/php-cs-fixer', 'fix', '--config=.php-cs-fixer.php')
    }
}