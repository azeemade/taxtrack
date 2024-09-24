<?php

namespace App\Http\Controllers\v1\Company\SharedActions;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\SharedServices\SharedActionService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class SharedActionController extends Controller
{
    protected $sharedActionService;

    public function __construct(SharedActionService $sharedActionService)
    {
        $this->sharedActionService = $sharedActionService;
    }

    public function __invoke(Request $request, $prefix,  $modelName, $id, $action)
    {
        $modelClass = config("route_model_map.$modelName");

        $model = $this->getModel($modelClass, $id);
        return $this->getAction($action, $model);
    }

    protected function getModel($modelClass, $id)
    {
        if ($modelClass && class_exists($modelClass)) {
            $model = new $modelClass();
            $modelRecord = $model::find($id);
            if (!$modelRecord) {
                throw new BadRequestException("Record not found");
            }
            return $modelRecord;
        }
        throw new BadRequestException("Model not found");
    }

    protected function getAction($action, $model)
    {
        if (method_exists($this, $action) && in_array($action, $model->allowedActions)) {
            return $this->$action($model);
        }

        throw new BadRequestException("Action not found");
    }

    public function duplicate(Model $model)
    {
        try {
            $record = $this->sharedActionService->duplicate($model);

            return JsonResponser::send(false, 'Duplication successful', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function sendReminder(Request $request)
    {
        try {
            $this->sharedActionService->sendReminder($request);

            return JsonResponser::send(false, 'Reminder sent successfully', null);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function emailEntity(Model $model)
    {
        try {
            $this->sharedActionService->emailEntity($model);

            return JsonResponser::send(false, 'Email sent to customer', null);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function deactivate(Model $model)
    {
        try {
            $this->sharedActionService->deactivate($model);

            return JsonResponser::send(false, 'Deactivation successful', null);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function delete(Model $model)
    {
        try {
            $this->sharedActionService->delete($model);

            return JsonResponser::send(false, 'Delete action successful', null);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function preview(Model $model)
    {
        try {
            return $preview = $this->sharedActionService->preview($model);

            // return JsonResponser::send(false, 'Preview generated successful', $preview);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage() . $th->getLine(), [], 500, $th);
        }
    }

    public function download(Model $model)
    {
        try {
            $preview = $this->sharedActionService->preview($model);

            return response($preview['file_contents'])
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $preview['file_name'] . '"');


            // return JsonResponser::send(false, 'Preview generated successful', $download);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }
}
