<?php

namespace Light\Filesystem\Node;

use League\Flysystem\MountManager;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Folder implements Node
{
    public function __construct(
        private readonly string $path,
        private readonly MountManager $mountManager
    ) {}

    #[Field()]
    public function getName(): string
    {
        // 去掉結尾斜線後取 basename
        return basename(rtrim($this->path, '/'));
    }

    #[Field]
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 取得資料夾下的所有子節點 (檔案或資料夾)
     * 透過依賴注入 FilesystemService 來處理轉換邏輯
     * @return Node[]
     */
    #[Field]
    public function getChildren(): array
    {
        // 呼叫 Service 內的 list 方法，將底層數據轉為 Node 物件陣列
        $nodes = [];
        // listContents 回傳一個迭代器
        $contents = $this->mountManager->listContents($this->path, false);

        foreach ($contents as $attributes) {
            $location = $attributes->path();

            if ($attributes->isDir()) {
                $nodes[] = new Folder($location, $this->mountManager);
            } else {
                // 這裡可以選擇傳入 metadata，或者讓 File 內部延遲獲取
                $nodes[] = new File($location, $this->mountManager);
            }
        }

        return $nodes;
    }

    #[Field]
    public function getLastModified(): int
    {
        return $this->mountManager->lastModified($this->path);
    }
}
