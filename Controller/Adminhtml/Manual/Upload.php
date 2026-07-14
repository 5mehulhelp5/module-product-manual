<?php
declare(strict_types=1);

namespace GDMexico\ProductManual\Controller\Adminhtml\Manual;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class Upload extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::products';

    private const UPLOAD_DIR = 'product/manuals';
    private const FIELD_NAME = 'assembly_manual';
    private const NORMALIZED_FIELD_NAME = 'product_manual_upload';
    private const MAX_FILE_SIZE = 10485760;

    /** @var UploaderFactory */
    private $uploaderFactory;

    /** @var Filesystem */
    private $filesystem;

    /** @var StoreManagerInterface */
    private $storeManager;

    public function __construct(
        Context $context,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager
    ) {
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    public function execute(): Json
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(
            ResultFactory::TYPE_JSON
        );

        try {
            $fileId = $this->resolveUploadedFileId();
            $uploadedFile = $_FILES[$fileId] ?? null;

            if (!is_array($uploadedFile)) {
                throw new LocalizedException(
                    __('No se recibió el archivo PDF.')
                );
            }

            $uploadError = (int) (
                $uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE
            );

            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new LocalizedException(
                    $this->getUploadErrorMessage($uploadError)
                );
            }

            if (
                (int) ($uploadedFile['size'] ?? 0)
                > self::MAX_FILE_SIZE
            ) {
                throw new LocalizedException(
                    __('El archivo supera el tamaño máximo permitido de 10 MB.')
                );
            }

            $uploader = $this->uploaderFactory->create([
                'fileId' => $fileId
            ]);

            $uploader->setAllowedExtensions(['pdf']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);

            $mediaDirectory = $this->filesystem->getDirectoryWrite(
                DirectoryList::MEDIA
            );

            $mediaDirectory->create(self::UPLOAD_DIR);

            $result = $uploader->save(
                $mediaDirectory->getAbsolutePath(
                    self::UPLOAD_DIR
                )
            );

            if (!is_array($result) || empty($result['file'])) {
                throw new LocalizedException(
                    __('No fue posible guardar el archivo PDF.')
                );
            }

            $relativeFile = self::UPLOAD_DIR
                . '/'
                . ltrim((string) $result['file'], '/');

            $mediaBaseUrl = rtrim(
                $this->storeManager
                    ->getStore()
                    ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
                '/'
            );

            return $resultJson->setData([
                'name' => (string) (
                    $result['name']
                    ?? basename($relativeFile)
                ),
                'file' => $relativeFile,
                'url' => $mediaBaseUrl
                    . '/'
                    . $relativeFile,
                'size' => (int) ($result['size'] ?? 0),
                'type' => 'application/pdf',
                'cookie' => [
                    'name' => $this->_getSession()->getName(),
                    'value' => $this->_getSession()->getSessionId(),
                    'lifetime' => $this->_getSession()
                        ->getCookieLifetime(),
                    'path' => $this->_getSession()
                        ->getCookiePath(),
                    'domain' => $this->_getSession()
                        ->getCookieDomain(),
                ],
            ]);
        } catch (\Throwable $exception) {
            return $resultJson->setData([
                'error' => $exception->getMessage(),
                'errorcode' => (int) $exception->getCode(),
            ]);
        }
    }

    /**
     * Magento puede enviar el archivo como:
     * assembly_manual, file, files o con otro nombre.
     */
    private function resolveUploadedFileId(): string
    {
        foreach (
            [self::FIELD_NAME, 'file', 'files']
            as $candidate
        ) {
            if (
                !isset($_FILES[$candidate])
                || !is_array($_FILES[$candidate])
            ) {
                continue;
            }

            if ($this->normalizeMultipleFilePayload($candidate)) {
                return self::NORMALIZED_FIELD_NAME;
            }

            if (!empty($_FILES[$candidate]['tmp_name'])) {
                return $candidate;
            }
        }

        foreach ($_FILES as $candidate => $fileData) {
            if (!is_array($fileData)) {
                continue;
            }

            if (
                $this->normalizeMultipleFilePayload(
                    (string) $candidate
                )
            ) {
                return self::NORMALIZED_FIELD_NAME;
            }

            if (!empty($fileData['tmp_name'])) {
                return (string) $candidate;
            }
        }

        throw new LocalizedException(
            __('No se recibió ningún archivo para cargar.')
        );
    }

    /**
     * Convierte una carga múltiple en una carga individual
     * compatible con Magento\Framework\File\Uploader.
     */
    private function normalizeMultipleFilePayload(
        string $fileId
    ): bool {
        $fileData = $_FILES[$fileId] ?? null;

        if (
            !is_array($fileData)
            || !is_array($fileData['tmp_name'] ?? null)
        ) {
            return false;
        }

        $index = array_key_first($fileData['tmp_name']);

        if (
            $index === null
            || empty($fileData['tmp_name'][$index])
        ) {
            return false;
        }

        $_FILES[self::NORMALIZED_FIELD_NAME] = [
            'name' => $fileData['name'][$index] ?? '',
            'full_path' => $fileData['full_path'][$index]
                ?? ($fileData['name'][$index] ?? ''),
            'type' => $fileData['type'][$index] ?? '',
            'tmp_name' => $fileData['tmp_name'][$index] ?? '',
            'error' => $fileData['error'][$index]
                ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileData['size'][$index] ?? 0,
        ];

        return true;
    }

    private function getUploadErrorMessage(
        int $errorCode
    ): \Magento\Framework\Phrase {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('El archivo excede el tamaño permitido por el servidor.');

            case UPLOAD_ERR_PARTIAL:
                return __('El archivo se cargó parcialmente. Intenta nuevamente.');

            case UPLOAD_ERR_NO_FILE:
                return __('No se recibió ningún archivo.');

            case UPLOAD_ERR_NO_TMP_DIR:
                return __('No existe el directorio temporal de carga.');

            case UPLOAD_ERR_CANT_WRITE:
                return __('No fue posible escribir el archivo en el servidor.');

            case UPLOAD_ERR_EXTENSION:
                return __('Una extensión de PHP detuvo la carga del archivo.');

            default:
                return __('Ocurrió un error al cargar el archivo.');
        }
    }
}
