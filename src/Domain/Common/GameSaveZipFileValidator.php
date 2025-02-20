<?php

namespace App\Domain\Common;

use App\Entity\ServerManager\GameSave;
use Doctrine\ORM\EntityManagerInterface;
use JsonSchema\Validator;
use Symfony\Component\HttpKernel\KernelInterface;
use \ZipArchive;

class GameSaveZipFileValidator
{

    private string $filePath;
    private ZipArchive $saveZip;
    private bool $valid = true;
    private array $errors = [];
    private ?string $configFileName;
    private ?string $dbDumpFileName;
    private GameSave $gameSave;
    private KernelInterface $kernel;
    private EntityManagerInterface $em;

    public function __construct(
        string $filePath,
        KernelInterface $kernel,
        EntityManagerInterface $em
    ) {
        $this->kernel = $kernel;
        $this->em = $em;
        $this->filePath = $filePath;
        $this->validate();
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorsAsString(): string
    {
        return implode("; ", $this->getErrors());
    }

    public function getZipArchive(): ZipArchive
    {
        return $this->saveZip;
    }

    public function getGameSave(): ?GameSave
    {
        return $this->gameSave;
    }

    public function getSessionConfigContents(): string
    {
        return $this->saveZip->getFromName($this->configFileName);
    }

    public function getGameListJsonContents(): string|bool
    {
        return $this->saveZip->getFromName('game_list.json');
    }

    public function getDbDumpFilename(): string
    {
        return $this->dbDumpFileName;
    }

    private function setError(string $message): void
    {
        $this->valid = false;
        $this->errors[] = $message;
    }

    private function validate(): void
    {
        if (!$this->openZip()) {
            $this->setError('Could not open archive. Are you sure it is a ZIP file?');
            return;
        }
        if (!$this->dbExportExists()) {
            $this->setError('Session DB export SQL file starting with "db_export_" does not exist in ZIP archive.');
        }
        if (!$this->sessionConfigExists()) {
            $this->setError(
                `Missing config file in ZIP archive starting with `.
                `"{$this->kernel->getContainer()->getParameter('app.session_config_name')}".`
            );
        } else {
            if (!$this->sessionConfigSchemaValid()) {
                $this->setError("Session config file {$this->configFileName} failed to pass JSON schema validation.");
            }
        }
        if (!$this->gameListExists()) {
            $this->setError('File game_list.json does not exist in ZIP archive.');
        } else {
            if (!$this->gameListValid()) {
                $this->setError('File game_list.json failed to pass GameList class validation.');
            }
        }
    }

    private function openZip(): bool
    {
        $this->saveZip = new ZipArchive();
        if ($this->saveZip->open($this->filePath) !== true) {
            return false;
        }
        return true;
    }

    private function dbExportExists(): bool
    {
        for ($i = 0; $i < $this->saveZip->numFiles; $i++) {
            $stat = $this->saveZip->statIndex($i);
            if (str_contains($stat['name'], 'db_export_')) {
                $this->dbDumpFileName = $stat['name'];
                return true;
            }
        }
        return false;
    }

    private function sessionConfigExists(): bool
    {
        $sessionConfigFileName = $this->kernel->getContainer()->getParameter('app.session_config_name');
        $sessionConfigFileNamePrefix = explode("%", $sessionConfigFileName)[0];
        for ($i = 0; $i < $this->saveZip->numFiles; $i++) {
            $stat = $this->saveZip->statIndex($i);
            if (str_contains($stat['name'], $sessionConfigFileNamePrefix)) {
                $this->configFileName = $stat['name'];
                return true;
            }
        }
        return false;
    }

    private function sessionConfigSchemaValid(): bool
    {
        $gameConfigContents = json_decode($this->getSessionConfigContents());
        $validator = new Validator();
        $validator->validate(
            $gameConfigContents,
            json_decode(
                file_get_contents($this->kernel->getProjectDir().'/src/Domain/SessionConfigJSONSchema.json')
            )
        );
        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $this->setError(sprintf("[%s] %s", $error['property'], $error['message']));
            }
            return false;
        }
        return true;
    }

    private function gameListExists(): bool
    {
        if ($this->getGameListJsonContents() === false) {
            return false;
        }
        return true;
    }

    private function gameListValid(): bool
    {
        try {
            $this->gameSave =(new GameListAndSaveSerializer($this->em))
                ->createGameSaveFromJson($this->getGameListJsonContents());
        } catch (\Throwable $e) {
            $this->setError('Unable to work with game_list.json contents from ZIP. '.$e->getMessage());
            return false;
        }
        return true;
    }
}
