<?php

declare(strict_types=1);

namespace App\Utilities\QueryHelpers;

use App\Utilities\Constants\Const_Date;
use Illuminate\Support\Facades\DB;

final class QH_DateTime
{
    public static function scopeDateRange($query, $date_range, $column_name)
    {
        $dates = explode(Const_Date::DATERANGE_SEPARATOR, $date_range);
        $start_date = _date_format_db($dates[0]);
        if (count($dates) > 1) {
            $end_date = _date_format_db($dates[1]);

            $query->whereDate($column_name, '>=', $start_date)
                ->whereDate($column_name, '<=', $end_date);
        } else {
            $query->whereDate($column_name, $start_date);
        }

        return $query;
    }

    public static function scopeDateRangeRelation($query, $date_range, $relation_name, $column_name)
    {
        $dates = explode(Const_Date::DATERANGE_SEPARATOR, $date_range);
        $start_date = _date_format_db($dates[0]);
        if (count($dates) > 1) {
            $end_date = _date_format_db($dates[1]);
            $query->whereRelation($relation_name, DB::raw(sprintf('DATE(%s)', $column_name)), '>=', $start_date)
                ->whereRelation($relation_name, DB::raw(sprintf('DATE(%s)', $column_name)), '<=', $end_date);
        } else {
            $query->whereRelation($relation_name, DB::raw(sprintf('DATE(%s)', $column_name)), $start_date);
        }

        return $query;
    }

    public static function scopeDateTimeRange($query, $date_range, $column_name)
    {
        $dates = explode(Const_Date::DATERANGE_SEPARATOR, $date_range);
        $start_date = _datetime_format_db($dates[0]);
        if (count($dates) > 1) {
            $end_date = _datetime_format_db($dates[1]);

            $query->where($column_name, '>=', $start_date)
                ->where($column_name, '<=', $end_date);
        } else {
            $query->where($column_name, $start_date);
        }

        return $query;
    }

    public static function scopeDateRangeTimeRelation($query, $date_range, $relation_name, $column_name)
    {
        $dates = explode(Const_Date::DATERANGE_SEPARATOR, $date_range);
        $start_date = _datetime_format_db($dates[0]);
        if (count($dates) > 1) {
            $end_date = _datetime_format_db($dates[1]);
            $query->whereRelation($relation_name, $column_name, '>=', $start_date)
                ->whereRelation($relation_name, $column_name, '<=', $end_date);
        } else {
            $query->whereRelation($relation_name, $column_name, $start_date);
        }

        return $query;
    }
}
