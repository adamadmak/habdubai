<?php

use App\Support\PublicAddress;

beforeEach(function (): void {
    PublicAddress::$resolver = fn (string $host): array => match ($host) {
        'jobs.example.com' => ['93.184.216.34'],
        'rebind.example.com' => ['93.184.216.34', '10.0.0.5'],
        'nothing.example.com' => [],
        default => ['169.254.169.254'],
    };
});

afterEach(function (): void {
    PublicAddress::$resolver = null;
});

test('public addresses are allowed', function (string $url): void {
    expect(PublicAddress::allows($url))->toBeTrue();
})->with(['https://jobs.example.com/rss', 'http://jobs.example.com:8080/x', 'https://93.184.216.34/', 'https://[2606:2800:220:1:248:1893:25c8:1946]/']);

test('private, loopback and cluster addresses are not', function (string $url): void {
    expect(PublicAddress::allows($url))->toBeFalse();
})->with([
    'loopback' => 'http://127.0.0.1/',
    'localhost' => 'http://localhost:8000/',
    'a localhost name' => 'http://api.localhost/',
    'private 10' => 'http://10.42.16.242:3000/',
    'private 172' => 'http://172.16.0.1/',
    'private 192' => 'http://192.168.1.1/',
    'link-local metadata' => 'http://169.254.169.254/latest/meta-data/',
    'carrier-grade NAT' => 'http://100.64.0.1/',
    'unspecified' => 'http://0.0.0.0/',
    'IPv6 loopback' => 'http://[::1]/',
    'IPv6 unique local' => 'http://[fd00::1]/',
    'IPv4 mapped' => 'http://[::ffff:10.0.0.1]/',
    'cluster name' => 'http://habesha-dubai-rw.db-17.svc.cluster.local:5432/',
    'a name that resolves privately' => 'http://sneaky.example.com/',
    'one private answer among public ones' => 'http://rebind.example.com/',
    'no answer at all' => 'http://nothing.example.com/',
    'another scheme' => 'file:///etc/passwd',
    'gopher' => 'gopher://jobs.example.com/',
    'nothing' => '',
]);
