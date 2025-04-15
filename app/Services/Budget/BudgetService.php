<?php

namespace App\Services\Budget;

use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\Budget;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class BudgetService
{
    public function create($request)
    {
        $record = Budget::create([...$request, "budgetID" => $this->generateBudgetId()]);

        $record->budgetItems()->createMany($request['line_items']);

        $record->budgetItems->each(function ($item, $key) use ($request) {
            $item->periods()->createMany($request['line_items'][$key]['periods']);
        });

        return $record;
    }

    public function delete($id)
    {
        $record = Budget::find($id);

        $record->budgetItems->each(function ($item) {
            $item->periods()->delete();
        });

        $record->budgetItems()->delete();


        $record->delete();
    }

    public function view($id)
    {
        return Budget::select('id', 'name', 'status', 'start_date', 'cycle', 'duration', 'description')
            ->with([
                'budgetItems:id,name,category_id,budget_id' => ['periods:id,month,year,amount,budget_item_id', 'category:id,name'],
            ])
            ->find($id);
    }

    public function overview($request)
    {
        $records = Budget::select('id', 'name', 'status', 'start_date', 'cycle', 'duration', 'budgetID', 'created_at')
            ->when($request->q, function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->q . '%')
                    ->orWhere('cycle', 'like', '%' . $request->q . '%');
            })
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy('name', 'asc');
                } else if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('start_date', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('start_date', 'desc');
                }
            })
            ->when(!empty($request->start_date) && !empty($request->end_date), function ($query) use ($request) {
                return $query->where('created_at', [$request->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    public function update($request, int $id)
    {
        $record = Budget::find($id);
        if (!$record) {
            throw new BadRequestException("Budget not found!", Response::HTTP_NOT_FOUND);
        }
        $record->update($request);

        $idsToKeep = [];
        foreach ($request['line_items'] as $lineItem) {
            unset($lineItem['periods']);
            if (isset($lineItem['id'])) {
                $idsToKeep[] = $lineItem['id'];
                $record->budgetItems()->where('id', $lineItem['id'])->update($lineItem);
            } else {
                $budgetItem = $record->budgetItems()->create($lineItem);
                $idsToKeep[] = $budgetItem['id'];
            }
        }
        $record->budgetItems()->whereNotIn('id', $idsToKeep)->delete();


        $record->budgetItems->each(function ($item, $key) use ($request) {
            $periodIdsToKeep = [];
            foreach ($request['line_items'][$key]['periods'] as $period) {
                if (isset($period['id'])) {
                    $periodIdsToKeep[] = $period['id'];
                    $item->periods()->where('id', $period['id'])->update($period);
                } else {
                    $newPeriod = $item->periods()->create($period);
                    $periodIdsToKeep[] = $newPeriod['id'];
                }
            }
            $item->periods()->whereNotIn('id', $periodIdsToKeep)->delete();
        });

        return $record;
    }

    public function computePeriods($request)
    {
        $periods = [];
        $startDate = Carbon::parse($request->start_date);
        $duration = (int) $request->duration;

        $cycleDurations = [
            "monthly" => 1,
            "quarterly" => 4,
            "annually" => 12
        ];

        if (!isset($cycleDurations[$request->cycle])) {
            throw new BadRequestException("Invalid cycle", Response::HTTP_BAD_REQUEST);
        }

        $endDate = $startDate->copy()->addMonths($duration * $cycleDurations[$request->cycle]);

        while ($startDate->lte($endDate)) {
            $periods[] = [
                'month' => $startDate->format('F'),
                'year' => $startDate->format('Y')
            ];
            $startDate->addMonths($cycleDurations[$request->cycle]);
        }
        return $periods;
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Budget name', 'ID', 'Created at', 'Start date', 'Duration', 'Cycle', 'Status', 'Budget items count', 'Total'];
        $records = $records->map(function ($record) {
            return [
                $record->name,
                $record->budgetID,
                Carbon::parse($record->created_at)->toFormattedDayDateString(),
                Carbon::parse($record->start_date)->toFormattedDayDateString(),
                $record->duration,
                $record->cycle,
                $record->status,
                $record->budgetItems->count(),
                $record->budget_total,
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'card_transactions_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'card_transactions_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function generateBudgetId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Budget',
            "modelField" => 'budgetID',
            "prefix" => 'bid-',
            "idLength" => 5,
        ]);
    }
}
