<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Exception;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use Icinga\Module\Eventtracker\File;
use Icinga\Module\Eventtracker\FrozenMemoryFile;
use Icinga\Module\Eventtracker\IssueFile;
use Icinga\Web\Notification;
use ipl\Html\Html;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

// stolen from ru*bn/storage
class FileUploadForm extends Form
{
    use TranslationHelper;

    /** @var UuidInterface[] */
    protected $issueUuids;
    private $db;

    /**
     * @param UuidInterface[] $issueUuids
     */
    public function __construct(array $issueUuids, $db)
    {
        $this->issueUuids = $issueUuids;
        $this->db = $db;
        $this->getAttributes()->set('enctype', 'multipart/form-data');
        $this->on(Form::ON_REQUEST, [$this, 'onRequest']);
    }

    public function assemble()
    {
        $this->setDefaultElementDecorator(new NoDecorator());
        $this->add(Html::tag('div', ['class' => 'eventtracker-file-drop-zone']));

        $this->addElement('text', 'uploaded_file[]', [
            'type'        => 'file',
            'label'       => $this->translate('Choose file'),
            // 'destination' => sys_get_temp_dir(),
            'ignore'      => true,
            'multiple'    => 'multiple',
        ]);

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Upload'),
        ]);
    }

    protected function processUploadedSource()
    {
        if (! array_key_exists('uploaded_file', $_FILES)) {
            throw new RuntimeException('Got no file');
        }
        $files = UploadedFile::processUploadedFileInfo($_FILES['uploaded_file']);
        foreach ($files as $uploadedFile) {
            if (is_uploaded_file($uploadedFile->tmp_name)) {
                $file = FrozenMemoryFile::fromBinary($uploadedFile->name, file_get_contents($uploadedFile->tmp_name));
                $db = $this->db;
                if (! File::exists($file, $db)) {
                    File::persist($file, $db);
                }

                // Deduplication based on content and filename.
                $key = sprintf('%s!%s', bin2hex($file->getChecksum()), $file->getName());
                if (isset($files[$key])) {
                    continue;
                }

                foreach ($this->issueUuids as $uuid) {
                    IssueFile::persist($uuid, $file, $db);
                }
                unlink($uploadedFile->tmp_name);
            } else {
                // add Error?
                throw new RuntimeException($uploadedFile->getErrorMessage());
            }
        }
    }

    public function onRequest(ServerRequestInterface $request) : void
    {
        if ($this->hasBeenSent()) {
            try {
                $this->processUploadedSource();
            } catch (Exception $e) {
                Notification::error($e->getMessage());
                return;
            }
        }
    }
}
