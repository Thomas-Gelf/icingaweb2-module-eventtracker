<?php

namespace Icinga\Module\Eventtracker\Web\Form;

// stolen from ru*bn/storage
class UploadedFile
{
    /** @var string */
    public $name;
    /** @var string */
    public $type; // Mime type as given by the browser
    /** @var ?int */
    public $error = null;
    /** @var string */
    public $tmp_name;
    /** @var int */
    public $size;

    public function getErrorMessage(): string
    {
        $filename = $this->name === '' ? 'your file' : $this->name;

        // no error and not an is_uploaded_file? Possible attack. Show generic error
        switch ($this->error) {
            // Die hochgeladene Datei überschreitet die in der php.ini-Anweisung upload_max_filesize festgelegte
            // Größe.
            // The uploaded file exceeds the upload_max_filesize directive in php.ini.
            case UPLOAD_ERR_INI_SIZE:
                return "$filename exceeds the upload_max_filesize directive in php.ini";

            // uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the html form
            // "The file you are trying to upload is too big.";
            // Die hochgeladene Datei überschreitet die im HTML-Formular mittels der Anweisung MAX_FILE_SIZE
            // angegebene maximale Dateigröße.
            // The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.
            case UPLOAD_ERR_FORM_SIZE:
                return "$filename exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";

            //uploaded file was only partially uploaded
            // "The file you are trying upload was only partially uploaded.";
            // The uploaded file was only partially uploaded.
            case UPLOAD_ERR_PARTIAL:
                return "$filename was only partially uploaded";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded";

            // Fehlender temporärer Ordner.
            // Missing a temporary folder.
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Failed to store $filename: temporary folder is missing";

            // Das Speichern der Datei auf die Festplatte ist fehlgeschlagen.
            // Failed to write file to disk
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to store $filename to disk";

            // A PHP extension stopped the file upload
            // Eine PHP-Erweiterung hat das Hochladen der Datei gestoppt
            case UPLOAD_ERR_EXTENSION:
                return "A server extension stopped uploading $filename";

            // "There was a problem with your upload.";, unknown error code $file['error']
            default:
                return "Failed to upload $filename";
        }
    }

    public static function fromArray(array $fileInfo) : UploadedFile
    {
        $self = new static();
        $self->name = $fileInfo['name'];
        $self->type = $fileInfo['type'];
        $self->error = $fileInfo['error'];
        $self->tmp_name = $fileInfo['tmp_name'];
        $self->size = $fileInfo['size'];

        return $self;
    }

    /**
     * @param array $fileInfo Usually $_FILES['file_upload_field_name']
     * @return UploadedFile[]
     */
    public static function processUploadedFileInfo(array $fileInfo): array
    {
        if (is_array($fileInfo['name'])) {
            $result = [];
            foreach ($fileInfo['name'] as $key => $name) {
                $result[] = $file = new static();
                $file->name = $name;
                $file->type = $fileInfo['type'][$key];
                $file->error = $fileInfo['error'][$key];
                $file->tmp_name = $fileInfo['tmp_name'][$key];
                $file->size = $fileInfo['size'][$key];
            }

            return $result;
        }

        return [static::fromArray($fileInfo)];
    }
}
