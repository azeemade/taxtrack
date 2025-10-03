<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;

class JournalCleanup
{
    /**
     * Delete journal + lines for a model that has journal_entry_id
     * Returns true if anything was deleted.
     */
    public function deleteForModel(object $model): bool
    {
        $jeId = (int) ($model->journal_entry_id ?? 0);
        if (!$jeId) {
            return false;
        }

        DB::transaction(function () use ($jeId, $model) {
            // If you use soft deletes on these tables, swap to update(['deleted_at' => now()])
            DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            DB::table('finance_journal_entries')->where('id', $jeId)->delete();

            // Null the pointer on the source model
            DB::table($model->getTable())->where('id', $model->id)->update(['journal_entry_id' => null]);
        });

        return true;
    }
}
