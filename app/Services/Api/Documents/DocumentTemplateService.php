<?php

namespace App\Services\Api\Documents;

use App\Enums\DocumentType;
use App\Enums\FileCollection;
use App\Models\User\DocumentTemplate;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Repositories\User\DocumentTemplateRepository;
use App\Services\Api\Files\RecordFileService;
use App\Services\Documents\DocumentPdf;
use App\Services\Documents\DocumentSample;
use App\Services\Documents\DocumentTypes;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

/**
 * Settings > Documents > Templates (Doc 06 section 14, Doc 11 section 20):
 * how each kind of document looks, for the apps the business has on. A change
 * applies to drafts and to documents numbered from then on - each numbered
 * one keeps the template it was numbered with. A preview draws a sample with
 * settings not saved yet.
 */
class DocumentTemplateService
{
    public function __construct(
        protected DocumentTemplateRepository $documentTemplateRepository,
        protected DocumentTypes $documentTypes,
        protected DocumentSample $documentSample,
        protected DocumentPdf $documentPdf,
        protected RecordFileService $recordFileService,
    ) {}

    /** @return list<array{template: DocumentTemplate, signature: ?WorkspaceFile}> one per kind, the defaults where unchanged */
    public function list(Workspace $workspace): array
    {
        $saved = $this->documentTemplateRepository->getFor($workspace);

        return array_map(fn (DocumentType $type) => $this->row($saved->get($type->value) ?? DocumentTemplate::defaultsFor($type)), $this->documentTypes->for($workspace));
    }

    /** The document type named in the address - 404 for one that is not, or whose app is off. */
    public function type(Workspace $workspace, string $value): DocumentType
    {
        return $this->documentTypes->named($workspace, $value);
    }

    /** $data is validated (see SaveDocumentTemplateRequest). */
    public function save(Workspace $workspace, DocumentType $type, array $data): array
    {
        $template = $this->documentTemplateRepository->save($workspace, $type, $data);

        ActivityLogService::record('updated', "Changed the {$type->label()} template", $workspace, ['source' => 'api']);

        return $this->row($template);
    }

    /**
     * A sample drawn with $data - validated like a save, and not saved - and
     * the template's signature, if it has one.
     *
     * @return array{0: string, 1: string} the PDF's file name and bytes
     */
    public function preview(Workspace $workspace, DocumentType $type, array $data): array
    {
        $template = $this->documentTemplateRepository->findFor($workspace, $type);
        $settings = [...$data, 'signature_file_id' => $template ? $this->documentTemplateRepository->signatureOf($template)?->id : null];

        return $type === DocumentType::CustomerReceipt
            ? $this->documentPdf->receipt($workspace, $this->documentSample->receipt($workspace, $settings))
            : $this->documentPdf->sales($workspace, $this->documentSample->sales($workspace, $type, $settings));
    }

    /**
     * A signature or stamp image for $type's documents. It replaces the one
     * before, which documents numbered with it then no longer show: like the
     * logo, a document keeps which image it had, not the image itself.
     *
     * @param  list<UploadedFile>  $uploads
     */
    public function addSignature(Workspace $workspace, DocumentType $type, array $uploads, string $field): array
    {
        // The image belongs to the template's row, so a template never changed is saved as it is first.
        $template = $this->documentTemplateRepository->findFor($workspace, $type)
            ?? $this->documentTemplateRepository->save($workspace, $type, Arr::except(DocumentTemplate::defaultsFor($type)->settings(null), 'signature_file_id'));

        $this->recordFileService->add($workspace, $template, FileCollection::Signature, $uploads, $field, $this->record($type));

        return $this->row($template);
    }

    public function removeSignature(Workspace $workspace, DocumentType $type): array
    {
        $template  = $this->documentTemplateRepository->findFor($workspace, $type);
        $signature = $template ? $this->documentTemplateRepository->signatureOf($template) : null;

        abort_unless($signature, 404, 'This template has no signature.');

        $this->recordFileService->remove($template, FileCollection::Signature, $signature, $this->record($type));

        return $this->row($template);
    }

    /** @return array{template: DocumentTemplate, signature: ?WorkspaceFile} */
    private function row(DocumentTemplate $template): array
    {
        return ['template' => $template, 'signature' => $this->documentTemplateRepository->signatureOf($template)];
    }

    /** How the activity log names it. */
    private function record(DocumentType $type): string
    {
        return "the {$type->label()} template";
    }
}
