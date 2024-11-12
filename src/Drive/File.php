<?php

namespace Light\Drive;

use League\Flysystem\FileAttributes;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type(name: "LightDriveFile")]
class File
{
    #[Field]
    public string $name;

    #[Field]
    public string $path;


    protected $file = null;
    protected $drive = null;

    public function __construct(Drive $drive, FileAttributes $file)
    {
        $this->drive = $drive;
        $this->file = $file;
        $this->path = $file->path();
        $this->name = basename($file->path());
    }

    #[Field]
    public function getLastModifiedHuman(): string
    {
        return date("Y-m-d H:i:s", $this->file->lastModified());
    }

    #[Field]
    public function getLastModified(): int
    {
        return $this->file->lastModified();
    }

    #[Field]
    public function getSize(): int
    {
        return $this->file->fileSize();
    }

    #[Field]
    public function getMime(): string
    {
        return $this->drive->getFileSystem()->mimeType($this->path);
    }

    #[Field]
    public function canPreview(): bool
    {
        if ($this->getMime() != "image/jpeg") {
            return false;
        }
        return true;
    }

    #[Field]
    public function getImagePath(): string
    {
        return "/drive/" . $this->drive->index . "/" . $this->path;
    }

    #[Field]
    public function getUrl(): string
    {
        return $this->drive->getFileUrl($this->path);
    }


    #[Field]
    public function getBase64Content(): string
    {
        return base64_encode($this->drive->getFileSystem()->read($this->path));
    }
}
