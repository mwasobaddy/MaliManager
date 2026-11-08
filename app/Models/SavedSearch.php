<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved unit-browse filter: location text, rent range and unit type, stored
 * verbatim so re-running is a redirect with the same query string.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array $filters
 */
class SavedSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'filters',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
