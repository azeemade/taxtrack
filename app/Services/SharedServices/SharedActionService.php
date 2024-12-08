<?php

namespace App\Services\SharedServices;

use App\Exceptions\BadRequestException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Mail\Shared\EntityDocumentEmail;
use App\Mail\Shared\EntityRemainderEmail;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SharedActionService
{
    public function duplicate(Model $model)
    {
        $newModel = $model->replicate();
        $newModel->parent_id = $model->id;
        $newModel->created_at = now();
        $newModel->save();
        $this->modelSpecificAction($model, $newModel);

        return $newModel;
    }

    public function sendReminder($request)
    {
        $mailData = new EntityRemainderEmail(
            $request['optional_cc'] ?? [],
            $request['email_copy'] ?? [],
            $request['subject'],
            $request['body'],
            $request['primary_email'],
            $request['main_file'] ?? null,
            $request['additional_attachments'] ?? null,
        );
        Mail::send($mailData);
        return true;
    }

    public function emailEntity(Model $model)
    {
        $preview = $this->preview($model);

        $data = [
            'document_url' => $preview['url'],
            'document_name' => $preview['filename'],
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
        } else {
            $model->is_active = !$model->is_active;
            $model->save();
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

    protected function modelSpecificAction(Model $model, Model $newModel)
    {
        if ($newModel instanceof \App\Models\Invoice) {
            $newModel->invoice_number = $this->generateModelId(
                'App\Models\Invoice',
                'invoiceID',
                'Inv',
                4
            );
            $newModel->save();
        } elseif ($newModel instanceof \App\Models\Quote) {
            $newModel->quoteID = $this->generateModelId(
                'App\Models\Quote',
                'quoteID',
                'qte',
                4
            );
            foreach ($model->lineItems as $lineItem) {
                $newLineItem = $lineItem->replicate();
                $newLineItem->documentable_id = $newModel->id;
                $newLineItem->save();
            }
            $newLineItem->save();
        }
        // elseif ($newModel instanceof \App\Models\Payment) {
        //     $newModel->payment_number = $modelClass->payment_number;
        //     $newModel->save();
        // } elseif ($newModel instanceof \App\Models\Receipt) {
        //     $newModel->receipt_number = $modelClass->receipt_number;
        //     $newModel->save();
        // }
    }

    protected function generateModelId($modelClass, $field, $prefix, $len)
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => $modelClass,
            "modelField" => $field,
            "prefix" => $prefix . '-',
            "idLength" => $len,
        ]);
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
