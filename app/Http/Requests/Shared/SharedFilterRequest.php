<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

class SharedFilterRequest extends FormRequest
{
    public function filters()
    {
        $q = $this->input('q');
        $status = $this->input('status');
        $sort_by = $this->input('sort_by');
        $date_filter = $this->input('date_filter');
        $start_date = $this->input('start_date');
        $end_date = $this->input('end_date');
        $is_recurring = $this->input('is_recurring', false);
        // $paginate = (bool) $this->input('paginate');
        $limit = $this->input('limit', 10);
        $export = $this->input('export');

        return compact('q', 'status', 'start_date', 'end_date', 'sort_by', 'paginate', 'export', 'limit', 'is_recurring');
    }
}
