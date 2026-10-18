<?php

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    $this->whitelistDirectory = sys_get_temp_dir().'/umg-whitelist-'.bin2hex(random_bytes(8));
    mkdir($this->whitelistDirectory, 0700);
    file_put_contents($this->whitelistDirectory.'/curl', <<<'BASH'
#!/usr/bin/env bash
printf '%s\n' "${@: -1}" >> "$MOCK_CURL_CALLS"
if [[ "$*" == *api.ipify.org* ]]; then
  printf '203.0.113.10'
elif [[ "${MOCK_HTTP_FAILURE:-0}" == 1 ]]; then
  exit 22
elif [[ "$*" == *direction=in* && "${MOCK_INCOMING_FAILURE:-0}" == 1 ]]; then
  printf '{"status":0}'
else
  printf '{"status":%s}' "${MOCK_API_STATUS:-1}"
fi
BASH);
    chmod($this->whitelistDirectory.'/curl', 0700);

    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/ci-cd.yml');
    $this->whitelistSteps = array_column($workflow['jobs']['deploy']['steps'], null, 'name');
    $this->runWhitelistStep = function (string $name, array $environment = []): Process {
        $process = new Process(['bash', '-e', '-o', 'pipefail', '-c', $this->whitelistSteps[$name]['run']], null, array_merge([
            'PATH' => $this->whitelistDirectory.PATH_SEPARATOR.getenv('PATH'),
            'DEPLOY_USER' => 'test-user',
            'CPANEL_TOKEN' => 'test-token',
            'CPANEL_HOST' => 'cpanel.example.test',
            'RUNNER_IP' => '203.0.113.10',
            'GITHUB_ENV' => $this->whitelistDirectory.'/github-env',
            'MOCK_CURL_CALLS' => $this->whitelistDirectory.'/calls',
            'MOCK_API_STATUS' => '1',
            'MOCK_HTTP_FAILURE' => '0',
            'MOCK_INCOMING_FAILURE' => '0',
        ], $environment));
        $process->mustRun();

        return $process;
    };
});

afterEach(function () {
    foreach (glob($this->whitelistDirectory.'/*') as $file) {
        unlink($file);
    }
    rmdir($this->whitelistDirectory);
});

it('records the runner address after successfully requesting SSH access', function () {
    ($this->runWhitelistStep)('Allow this runner through the SSH whitelist');

    expect(file_get_contents($this->whitelistDirectory.'/github-env'))->toBe("RUNNER_IP=203.0.113.10\n");
});

it('rejects an unsuccessful whitelist addition even when HTTP succeeds', function (array $environment) {
    expect(fn () => ($this->runWhitelistStep)('Allow this runner through the SSH whitelist', $environment))
        ->toThrow(ProcessFailedException::class);
})->with([
    'API rejection' => [['MOCK_API_STATUS' => '0']],
    'HTTP failure' => [['MOCK_HTTP_FAILURE' => '1']],
]);

it('removes both incoming and outgoing runner authorizations', function () {
    ($this->runWhitelistStep)('Remove this runner from the SSH whitelist');

    $calls = file_get_contents($this->whitelistDirectory.'/calls');
    expect($calls)->toContain('direction=in', 'direction=out');
});

it('attempts both cleanup directions and reports failures', function (array $environment) {
    expect(fn () => ($this->runWhitelistStep)('Remove this runner from the SSH whitelist', $environment))
        ->toThrow(ProcessFailedException::class);

    $calls = file_get_contents($this->whitelistDirectory.'/calls');
    expect($calls)->toContain('direction=in', 'direction=out');
})->with([
    'incoming API rejection' => [['MOCK_INCOMING_FAILURE' => '1']],
    'HTTP failure' => [['MOCK_HTTP_FAILURE' => '1']],
]);
