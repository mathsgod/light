<?php

namespace Light\Type;

use League\Flysystem\FileAttributes;
use League\Flysystem\MountManager;
use Light\App;
use Light\Filesystem\Node\File;
use Light\Filesystem\Node\Folder;
use Light\Filesystem\Node\Node;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type()]
class Filesystem
{
    #[Field]
    /**
     * @return array<int,mixed>
     */
    public function list(#[Autowire] App $app)
    {
        return $app->getFSConfig();
    }

    #[Field]
    /**
     * @return array<int,mixed>
     */
    public function getTypes()
    {

        $types = [];
        $type = [
            "label" => "Local Filesystem",
            "name" => "local",
            "options" => [
                "location" => ["type" => "string", "description" => "Root Path", "required" => true, "placeholder" => "uploads"],
                "public_url" => ["type" => "string", "description" => "Public URL base path"],
            ],
        ];

        $types[] = $type;


        //s3
        $types[] = [
            "label" => "Amazon S3",
            "name" => "s3",
            "disabled" => !\Composer\InstalledVersions::isInstalled("league/flysystem-aws-s3-v3"),
            "options" => [
                "region" => ["type" => "string", "description" => "AWS Region", "required" => true],
                "endpoint" => ["type" => "string", "description" => "AWS Endpoint", "required" => true],
                "bucket" => ["type" => "string", "description" => "S3 Bucket Name", "required" => true],
                "access_key" => ["type" => "string", "description" => "AWS Access Key", "required" => true],
                "secret_key" => ["type" => "string", "description" => "AWS Secret Key", "required" => true],
                "prefix" => ["type" => "string", "description" => "Path Prefix inside the bucket"],
                "public_url" => ["type" => "string", "description" => "Public URL base path"],
            ],
        ];


        //hostlink storage
        $types[] = [
            "label" => "Hostlink Storage",
            "name" => "hostlink",
            "disabled" => !\Composer\InstalledVersions::isInstalled("hostlink/hostlink-storage-adapter"),
            "options" => [
                "token" => ["type" => "string", "description" => "Hostlink Access Token", "required" => true],
                "endpoint" => ["type" => "string", "description" => "Hostlink API Endpoint", "required" => true],
                "public_url" => ["type" => "string", "description" => "Public URL base path"],
            ],
        ];


        //alphasnow/aliyun-oss-flysystem
        $types[] = [
            "label" => "Aliyun OSS",
            "name" => "oss",
            "disabled" => !\Composer\InstalledVersions::isInstalled("alphasnow/aliyun-oss-flysystem"),
            "options" => [
                "access_key_id" => ["type" => "string", "description" => "Access Key ID", "required" => true],
                "access_key_secret" => ["type" => "string", "description" => "Access Key Secret", "required" => true],
                "endpoint" => ["type" => "string", "description" => "OSS Endpoint", "required" => true],
                "bucket" => ["type" => "string", "description" => "OSS Bucket Name", "required" => true],
                "public_url" => ["type" => "string", "description" => "Public URL base path"],
            ],
        ];



        return $types;
    }

    #[Field]
    public function node(string $location, #[Autowire()] MountManager $mountManager): ?Node
    {
        if ($mountManager->directoryExists($location)) {
            return new Folder($location);
        } elseif ($mountManager->fileExists($location)) {
            return new File($location);
        }
        return null;
    }

    #[Field]
    public function exists(string $location, #[Autowire()] MountManager $mountManager): bool
    {
        return $mountManager->fileExists($location) || $mountManager->directoryExists($location);
    }

    #[Field]
    /**
     * @return Node[]
     */
    public function find(
        #[Autowire()] MountManager $mountManager,
        #[Autowire()] App $app,
        ?string $search,
        ?string $label = null, // 新增標籤參數


    ): array {
        $nodes = [];

        foreach ($app->getFSConfig() as $fs) {
            $location = $fs["name"] . "://";
            // listContents(..., true) 是遞迴搜尋
            foreach ($mountManager->listContents($location, true) as $item) {
                //skip if starts with dot
                if (str_starts_with(basename($item->path()), '.')) {
                    continue;
                }


                // 1. 關鍵字過濾
                if ($search && stripos($item->path(), $search) === false) {
                    continue;
                }

                // 2. 標籤 (MimeType) 過濾
                if ($label && !$this->matchesLabel($item, $label, $mountManager)) {
                    continue;
                }

                // 3. 實例化
                if ($item->isDir()) {
                    $nodes[] = new Folder($item->path(), [
                        'last_modified' => $item->lastModified(),
                    ]);
                } elseif ($item instanceof FileAttributes) {
                    $nodes[] = new File($item->path(), [
                        'size' => $item->fileSize(),
                        'last_modified' => $item->lastModified(),
                    ]);
                }
            }
        }

        return $nodes;
    }

    /**
     * 內部輔助函數：判斷檔案是否符合標籤
     */
    private function matchesLabel($item, string $label, MountManager $mountManager): bool
    {
        if (!$item->isFile()) return false;
        //if start with dot, return false


        // 獲取 mimeType (例如: image/jpeg)
        $mime = $mountManager->mimeType($item->path());

        return match ($label) {
            'image'    => str_starts_with($mime, 'image/'),
            'video'    => str_starts_with($mime, 'video/'),
            'audio'    => str_starts_with($mime, 'audio/'),
            'document' => in_array($mime, ['application/pdf', 'application/msword', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint']),
            default    => false,
        };
    }
}
