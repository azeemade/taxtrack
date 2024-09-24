<?php

namespace App\Services\SharedServices;

use App\Exceptions\BadRequestException;
use App\Helpers\FileUploadHelper;
use App\Mail\Shared\EntityDocumentEmail;
use App\Mail\Shared\VendorRemainderEmail;
use App\Models\Vendor;
use App\Traits\SharedActionTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SharedActionService
{
    public function duplicate(Model $model)
    {
        $newModel = $model->replicate();
        $newModel->parent_id = $model->id;
        $newModel->created_at = now();
        $newModel->save();
        return $newModel;
    }

    public function sendReminder(Request $request)
    {
        $vendor = Vendor::find($request->vendor_id);

        $mailData = new VendorRemainderEmail(
            $request->cc,
            $request->email_me_a_copy,
            $request->email_subject,
            $request->email_body
        );

        Mail::to($vendor->primary_email)->send($mailData);
        return true;
    }

    public function emailEntity(Model $model)
    {
        $preview = $this->preview($model);

        $data = [
            'document_url' => $preview['url'],
            'document_name' => $preview['name'],
            'model' => $model->previewables['model'],
            'company' => $model->previewables['company'],
            'entity' => $model->previewables['entity_data'],
        ];

        Mail::to($model->previewables['entity_data']['email'])
            ->send(new EntityDocumentEmail($data));
    }

    public function deactivate(Model $model)
    {
        if (method_exists($model, 'deactivate')) {
            $model->deactivate();
            return true;
        }
        throw new BadRequestException("Model does not support deactivation");
    }

    public function delete(Model $model)
    {
        $model->delete();
        return true;
    }

    public function preview(Model $model)
    {
        $modelName = class_basename($model);

        $pdf = Pdf::loadView('previews.document_preview', ['previewables' => $model->previewables])->setPaper('a4', 'portrait');
        $fileName = date('Ymd-His') . '-' . $modelName . '-' . $model->previewables['id'] . '.pdf';
        $file = $pdf->stream($fileName);
        $newDoc = base64_encode($file);
        $fileUrl = FileUploadHelper::singleStringFileUpload($newDoc, "Invoice");
        $fileContents = file_get_contents($fileUrl);

        $model->preview_link = $fileUrl;
        $model->save();

        return ['url' => $fileUrl, 'filename' => $fileName, 'file_contents' => $fileContents];
    }

    // public function download(Model $model)
    // {
    //     if (empty($model->preview_link)) {
    //         $this->generatePreview($model);
    //     }

    //     return response()->download(storage_path('app/public' . parse_url($model->preview_link, PHP_URL_PATH)));

    //     $$fileName = basename($model);

    //     // Use file_get_contents() to fetch the file's contents
    //     $fileContents = file_get_contents($fileUrl);

    //     // Check if the file exists at the URL
    //     if ($fileContents === false) {
    //         return response()->json(['message' => 'File could not be found or accessed'], 404);
    //     }

    //     // Return the file as a download
    //     return response($fileContents)
    //         ->header('Content-Type', 'application/pdf') // assuming it's a PDF
    //         ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    // }
}
