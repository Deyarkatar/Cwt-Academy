<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use App\Services\Payments\ManualPaymentService;
use App\Support\Security\UrlHelper;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AdminPaymentProofDownloadController extends Controller
{
    public function download(int $id): Response
    {
        $proof = PaymentProof::findOrFail($id);

        if (! auth()->user()?->can('download', $proof)) {
            abort(403, 'Unauthorized');
        }

        $path = $proof->proof_file_path;
        $disk = ManualPaymentService::storageDisk();

        if (! $path || ! UrlHelper::safePaymentProofPath($path) || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $response = Storage::disk($disk)->download($path);
        if ($proof->proof_mime) {
            $response->headers->set('Content-Type', $proof->proof_mime);
        }
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.basename($path).'"');

        return $response;
    }
}
