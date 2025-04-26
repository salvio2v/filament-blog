<?php

namespace Firefly\FilamentBlog\Models;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Firefly\FilamentBlog\Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'post_ids',
    ];

    protected $casts = [
       // 'id' => 'integer',
    ];

    public function posts(): BelongsToMany
    {

        return $this->belongsToMany(Post::class, config('filamentblog.tables.prefix'). 'tags');
    }

    public static function getForm(): array
    {
        return [
            TextInput::make('name')
                ->live(true)->afterStateUpdated(fn(Set $set, ?string $state) => $set(
                    'slug',
                    Str::slug($state)
                ))
                ->unique(config('filamentblog.tables.prefix').'tags', 'name', null, 'id')
                ->required()
                ->maxLength(50),

            TextInput::make('slug')
                ->unique(config('filamentblog.tables.prefix').'tags', 'slug', null, 'id')
                ->readOnly()
                ->maxLength(155),
        ];
    }

    protected static function newFactory()
    {
        return new TagFactory();
    }

    public function getTable()
    {
        return config('filamentblog.tables.prefix') . 'tags';
    }
}
