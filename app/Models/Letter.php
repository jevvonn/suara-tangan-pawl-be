<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Letter extends Model
{
    /**
     * The attributes that are mass assignable.
     * `category` is a generated column and must never be written.
     *
     * @var list<string>
     */
    protected $fillable = [
        'symbol',
        'image_url',
        'created_by',
    ];

    /**
     * Always store the symbol in uppercase.
     */
    protected function symbol(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtoupper(trim($value)),
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
