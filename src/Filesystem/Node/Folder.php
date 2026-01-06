<?php

namespace Light\Filesystem\Node;

use League\Flysystem\FileAttributes;
use League\Flysystem\MountManager;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Folder implements Node
{
    public function __construct(
        private readonly string $location,
        private readonly ?array $metadata = null,
    ) {}


    #[Field]
    public function getTotalSize(#[Autowire] MountManager $mountManager): int
    {
        $size = 0;
        foreach ($mountManager->listContents($this->location, true) as $file) {
            if ($file instanceof FileAttributes) {
                $size += intval($file->fileSize());
            }
        }
        return $size;
    }

    #[Field()]
    public function getName(): string
    {
        // 去掉結尾斜線後取 basename
        return basename(rtrim($this->location, '/'));
    }

    #[Field]
    public function getPath(): string
    {
        return ltrim(explode('://', $this->location)[1] ?? $this->location, '/');
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * 取得資料夾下的所有子節點 (檔案或資料夾)
     * 透過依賴注入 FilesystemService 來處理轉換邏輯
     * @return Node[]
     */
    #[Field]
    public function getChildren(#[Autowire] MountManager $mountManager): array
    {
        // 呼叫 Service 內的 list 方法，將底層數據轉為 Node 物件陣列
        $nodes = [];
        // listContents 回傳一個迭代器
        $contents = $mountManager->listContents($this->location, false);

        foreach ($contents as $attributes) {
            $location = $attributes->path();

            //skip name start with .
            if (basename($location)[0] === '.') {
                continue;
            }

            if ($attributes instanceof \League\Flysystem\DirectoryAttributes) {
                $nodes[] = new Folder($location, [
                    'last_modified' => $attributes->lastModified(),
                ]);
            } elseif ($attributes instanceof \League\Flysystem\FileAttributes) {
                // 這裡可以選擇傳入 metadata，或者讓 File 內部延遲獲取
                $nodes[] = new File($location, [
                    'size' => $attributes->fileSize(),
                    'last_modified' => $attributes->lastModified(),
                ]);
            }
        }

        return $nodes;
    }

    #[Field]
    public function getLastModified(#[Autowire] MountManager $mountManager): int
    {
        if (isset($this->metadata['last_modified'])) {
            return $this->metadata['last_modified'];
        }
        return $mountManager->lastModified($this->location);
    }
}
