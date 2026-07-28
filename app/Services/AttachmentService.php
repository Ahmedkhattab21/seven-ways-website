<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function store(Model $attachable, UploadedFile $file, ?string $category): Attachment
    {
        abort_unless((int) $attachable->company_id === (int) $this->tenant->companyId(), 403);

        return DB::transaction(function () use ($attachable, $file, $category) {
            $storedName = Str::uuid().'.'.$file->guessExtension();
            $path = $file->storeAs('private/attachments/'.$this->tenant->companyId(), $storedName, 'local');
            $attachment = new Attachment([
                'category' => $category, 'original_name' => basename($file->getClientOriginalName()),
                'stored_name' => $storedName, 'disk' => 'local', 'path' => $path,
                'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
            ]);
            $attachment->forceFill([
                'company_id' => $this->tenant->companyId(), 'uploaded_by' => $this->tenant->user()?->id,
            ]);
            $attachable->attachments()->save($attachment);
            $this->audit->record('attachment.uploaded', $attachable, ['attachment_id' => $attachment->id]);

            return $attachment;
        });
    }

    public function delete(Attachment $attachment): void
    {
        abort_unless((int) $attachment->company_id === (int) $this->tenant->companyId(), 403);

        DB::transaction(function () use ($attachment) {
            $attachment->delete();
            $this->audit->record('attachment.deleted', $attachment->attachable, ['attachment_id' => $attachment->id]);
        });

        Storage::disk($attachment->disk)->delete($attachment->path);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->assertPrivatePath($attachment);
        $this->audit->record('attachment.downloaded', $attachment->attachable, [
            'attachment_id' => $attachment->id,
        ]);
        $downloadName = basename((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $attachment->original_name));

        return Storage::disk('local')->download($attachment->path, $downloadName, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function assertPrivatePath(Attachment $attachment): void
    {
        $prefix = 'private/attachments/'.$this->tenant->companyId().'/';

        abort_unless(
            $attachment->disk === 'local'
            && str_starts_with($attachment->path, $prefix)
            && $attachment->path === $prefix.basename($attachment->path),
            404
        );
    }
}
