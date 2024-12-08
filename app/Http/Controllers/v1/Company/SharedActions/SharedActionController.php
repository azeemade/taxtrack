<?php

namespace App\Http\Controllers\v1\Company\SharedActions;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedReminderRequest;
use App\Models\Customer;
use App\Models\Vendor;
use App\Responser\JsonResponser;
use App\Services\SharedServices\SharedActionService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;

class SharedActionController extends Controller
{
    protected $sharedActionService;

    public function __construct(SharedActionService $sharedActionService)
    {
        $this->sharedActionService = $sharedActionService;
    }

    // public function __invoke(Request $request, $prefix,  $modelName, $id, $action)
    public function __invoke($prefix = null,  $modelName, $id, $action = null)
    {
        try {
            $modelClass = config("route_model_map.$modelName");

            $model = $this->getModel($modelClass, $id);
            $response = $this->getAction($action, $model, request()->all(), $id);

            if (in_array($action, ["preview", "download"])) {
                return $response;
            }

            return JsonResponser::send(false, $response["message"], $response["record"], Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    protected function getModel($modelClass, $id)
    {
        if ($modelClass && class_exists($modelClass)) {
            $model = new $modelClass();

            $modelRecord = $model::find($id);
            if (!$modelRecord) {
                throw new BadRequestException("Record not found", Response::HTTP_NOT_FOUND);
            }
            return $modelRecord;
        }
        throw new BadRequestException("Model not found", Response::HTTP_NOT_FOUND);
    }

    protected function getAction($action, $model, $request = null, $id = null)
    {
        if ($model->allowedActions && method_exists($this, $action) && in_array($action, $model->allowedActions)) {
            return $this->$action($model, $request, $id);
        }

        throw new BadRequestException("Action not found", Response::HTTP_NOT_FOUND);
    }

    public function duplicate(Model $model)
    {
        $record = $this->sharedActionService->duplicate($model);

        return ["message" => 'Duplication successful', "record" => $record];
    }

    public function remind(Model $model, $request)
    {
        $request = app()->make(SharedReminderRequest::class);

        $this->sharedActionService->sendReminder([...$request->validated(), 'primary_email' => $model->customer->email]);

        return ["message" => 'Reminder sent successfully', "record" => null];
    }

    public function email(Model $model)
    {
        $this->sharedActionService->emailEntity($model);

        return ["message" => 'Email sent to customer', "record" => null];
    }

    public function deactivate(Model $model)
    {
        $this->sharedActionService->deactivate($model);

        return ["message" => 'Deactivation successful', "record" => null];
    }

    public function delete(Model $model)
    {
        $this->sharedActionService->delete($model);

        return ["message" => 'Deleted successful', "record" => null];
    }

    public function preview(Model $model)
    {
        return $this->sharedActionService->preview($model);
    }

    public function download(Model $model)
    {
        $preview = $this->sharedActionService->preview($model);

        return response($preview['file_contents'])
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $preview['filename'] . '"');
    }

    protected function cleanRequest($request): Request | SharedReminderRequest
    {
        // dd($request);
        return $request;
    }
}
