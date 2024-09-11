<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\TaskFormRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * Get all tasks.
     *
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $records = Task::where('user_id', Auth::user()->id)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Records found successfully',
                'data' => $records,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'data' => $th->getMessage()
            ]);
        }
    }

    /**
     * View task details.
     *
     * @param  string $id
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $record = Task::where('user_id', Auth::user()->id)->find($id);
            if (!$record) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Record not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Record found successfully',
                'data' => $record,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'data' => []
            ]);
        }
    }

    /**
     * Create new task.
     *
     * @param  Illuminate\Http\Request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(TaskFormRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $record = Task::create([
                'title' => $request->title,
                'description' => $request->description,
                'user_id' => Auth::user()->id,
            ]);
            
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Record created successfully',
                'data' => $record,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'data' => []
            ]);
        }
    }

    /**
     * Update task.
     *
     * @param  Illuminate\Http\Request
     * @param  integer $id
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(TaskFormRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $record = Task::where('user_id', Auth::user()->id)->find($id);
            if (!$record) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Record not found',
                    'data' => []
                ], 404);
            }

            $record->update([
                'title' => $request->title,
                'description' => $request->description,
                'completed' => $request->has('completed') ? $request->completed :  $record->completed,
            ]);

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Record updated successfully',
                'data' => $record,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'data' => []
            ]);
        }
    }

    /**
     * Delete task.
     *
     * @param  integer $id
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $record = Task::where('user_id', Auth::user()->id)->find($id);
            if (!$record) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Record not found',
                    'data' => []
                ], 404);
            }

            $record->delete();

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Record deleted successfully',
                'data' => [],
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'data' => []
            ]);
        }
    }
}
