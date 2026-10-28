<?php

namespace Kern\Media\Chunks\Receiver;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Kern\Media\Chunks\Exceptions\UploadFailedException;
use Kern\Media\Chunks\Handler\AbstractHandler;
use Kern\Media\Chunks\Save\AbstractSave;
use Kern\Media\Chunks\Storage\ChunkStorage;

class FileReceiver
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var UploadedFile|null
     */
    protected $file;

    /**
     * The handler that detects what upload process is being used.
     */
    protected ?AbstractHandler $handler = null;

    /**
     * The chunk storage.
     *
     * @var ChunkStorage
     */
    protected $chunkStorage;

    /**
     * The file receiver for the given file index.
     *
     * @param  string|UploadedFile  $fileIndexOrFile  the desired file index to use in request or the final UploadedFile
     * @param  Request  $request  the current request
     * @param  string  $handlerClass  the handler class name for detecting the file upload
     * @param  ChunkStorage|null  $chunkStorage  the chunk storage, on null will use the instance from app container
     *
     * @throws UploadFailedException
     */
    public function __construct($fileIndexOrFile, Request $request, $handlerClass, $chunkStorage = null)
    {
        $this->request = $request;
        $this->file = is_object($fileIndexOrFile) ? $fileIndexOrFile : $request->file($fileIndexOrFile);
        $this->chunkStorage = $chunkStorage === null ? ChunkStorage::storage() : $chunkStorage;

        if ($this->isUploaded()) {
            if (! $this->file->isValid()) {
                throw new UploadFailedException($this->file->getErrorMessage());
            }

            $this->handler = new $handlerClass($this->request, $this->file);
        }
    }

    /**
     * Checks if the file was uploaded.
     */
    public function isUploaded(): bool
    {
        return is_object($this->file) && $this->file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Tries to handle the upload request. If the file is not uploaded, returns false. If the file
     * is present in the request, it will create the save object.
     *
     * If the file in the request is chunk, it will create the `ChunkSave` object
     * which doesn't do anything at this moment.
     *
     * @return bool|AbstractSave
     */
    public function receive()
    {
        if (is_object($this->handler) === false) {
            return false;
        }

        return $this->handler->startSaving($this->chunkStorage);
    }
}
