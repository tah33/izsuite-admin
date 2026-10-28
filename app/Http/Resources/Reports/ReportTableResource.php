<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A report as its page shows it (Doc 07): its title and period, its columns,
 * each row's values - only those its columns show, and the page of the
 * record it comes from - its totals, headline figures, notes and chart.
 * Wraps a ReportTable.
 */
class ReportTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $table = $this->resource;
        $keys  = array_column($table->columns, 'key');

        return [
            'title'   => $table->title,
            'period'  => $table->period,
            'columns' => $table->columns,
            'rows'    => array_map(fn (array $row) => [...array_intersect_key($row, array_flip($keys)), 'link' => $row['link'] ?? null], $table->rows),
            'totals'  => $table->totals,
            'summary' => $table->summary,
            'notes'   => $table->notes,
            'chart'   => $table->chart,
        ];
    }
}
