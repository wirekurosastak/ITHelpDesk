<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AuthorizesTicketAccess;
use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentController extends Controller
{
    use AuthorizesTicketAccess;

    public function store(StoreAttachmentRequest $request, Ticket $ticket): JsonResponse
    {
        $user = auth('api')->user();

        $this->authorizeTicketAccess($ticket, $user);

        $file = $request->file('file');
        $path = $file->store('attachments');

        try {
            $attachment = Attachment::create([
                'ticket_id' => $ticket->id,
                'uploaded_by' => $user->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
            ]);
        } catch (Throwable $e) {
            Storage::delete($path);

            throw $e;
        }

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'data' => $attachment->load('uploader'),
        ], 201);
    }

    public function download(Attachment $attachment): StreamedResponse|JsonResponse
    {
        $ticket = $attachment->ticket;
        $user = auth('api')->user();

        $this->authorizeTicketAccess($ticket, $user);

        if (! Storage::exists($attachment->file_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return Storage::download($attachment->file_path, $attachment->original_name);
    }
}
