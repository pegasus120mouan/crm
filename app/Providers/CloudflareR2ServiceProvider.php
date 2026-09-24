<?php

namespace App\Providers;

use Aws\CommandInterface;
use Aws\Middleware;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as S3Adapter;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Visibility;
use Psr\Http\Message\RequestInterface;

class CloudflareR2ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('r2', function ($app, array $config) {
            $client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'] ?? 'auto',
                'endpoint' => $config['endpoint'],
                'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? true,
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
                'request_checksum_calculation' => 'when_required',
                'response_checksum_validation' => 'when_required',
                'use_aws_shared_config_files' => false,
            ]);

            $client->getHandlerList()->appendInit(
                Middleware::mapCommand(function (CommandInterface $command) {
                    $command->offsetUnset('ACL');

                    return $command;
                }),
                'r2-strip-acl'
            );

            $client->getHandlerList()->appendBuild(
                Middleware::mapRequest(function (RequestInterface $request) {
                    return $request
                        ->withoutHeader('X-Amz-User-Agent')
                        ->withoutHeader('x-amz-user-agent');
                }),
                'r2-strip-ua'
            );

            $adapter = new S3Adapter(
                $client,
                $config['bucket'],
                (string) ($config['root'] ?? ''),
                null,
                null,
                $config['options'] ?? [],
                false
            );

            return new AwsS3V3Adapter(
                new Flysystem($adapter, [
                    'visibility' => Visibility::PRIVATE,
                    'retain_visibility' => false,
                    'url' => $config['url'] ?? null,
                ]),
                $adapter,
                $config,
                $client
            );
        });
    }
}
