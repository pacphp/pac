<?php
declare(strict_types=1);

namespace Pac\Factory\Filesystem;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;

class S3V3Factory
{
    public function create(array $config): Filesystem
    {
        $client = new S3Client(
            [
                'credentials' => [
                    'key'    => $config['key'],
                    'secret' => $config['secret'],
                ],
                'region'      => $config['region'],
                'version'     => $config['version'],
            ]
        );

        $adapter = new AwsS3Adapter($client, $config['bucket'], $config['prefix'] ?? '');

        return new Filesystem($adapter);
    }
}
