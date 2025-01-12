<?php

namespace App\Services\TaxRate;

use App\Exceptions\BadRequestException;
use App\Models\TaxRate;
use Illuminate\Http\Response;

class TaxRateService
{

    public function list($request)
    {
        $records = TaxRate::query()
            ->select('id', 'name', 'display_name', 'rate', 'is_active', 'created_at')
            ->withCount(
                [
                    'components as account_applicable_count'
                ]
            )
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy('name', 'asc');
                } else if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('created_at', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('created_at', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('is_active', $request->status);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('name', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    public function view($id)
    {
        $record = TaxRate::with('components:id,name,rate,non_recoverable,compound,tax_rate_id')->find($id);
        if (!$record) {
            throw new BadRequestException('Record not found', Response::HTTP_NOT_FOUND);
        }

        return $record;
    }

    public function create($request)
    {
        $record = TaxRate::create($request);

        $record->components()->createMany($request['components']);

        return $record;
    }

    public function update($request, $id)
    {
        $record = TaxRate::find($id);
        $record->update($request);
        $record->components()->delete();
        $record->components()->createMany($request['components']);
        return $record;
    }
}
