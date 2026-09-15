<?php

namespace App\Http\Resources\Concerns;

use Carbon\Carbon;
use DateTimeInterface;

trait FormatsDateTime
{
    protected function dateTime(DateTimeInterface|string|null $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)
            ->timezone(config('app.timezone', 'Asia/Yangon'))
            ->format('Y-m-d H:i:s');
    }
}
