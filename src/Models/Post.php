<?php

namespace Firefly\FilamentBlog\Models;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Filament\Forms\Set;
use FilamentTiptapEditor\TiptapEditor;
use Firefly\FilamentBlog\Database\Factories\PostFactory;
use Firefly\FilamentBlog\Enums\PostStatus;
use MongoDB\Laravel\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'sub_title',
        'body',
        'status',
        'published_at',
        'scheduled_for',
        'cover_photo_path',
        'photo_alt_text',
        'user_id',
        'category_ids',
        'tag_ids',
    ];

    protected $dates = [
        'scheduled_for',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        # 'id' => 'integer',
        'published_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'status' => PostStatus::class,
        # 'user_id' => 'integer',
    ];

    protected static function newFactory()
    {
        return new PostFactory();
    }


    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class,config('filamentblog.tables.prefix'). 'categories');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class,config('filamentblog.tables.prefix'). 'tags');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('filamentblog.user.model'), config('filamentblog.user.foreign_key'));
    }

    public function seoDetail()
    {
        return $this->hasOne(SeoDetail::class);
    }

    public function isNotPublished()
    {
        return ! $this->isStatusPublished();
    }

    public function scopePublished(Builder $query)
    {
        return $query->where('status', PostStatus::PUBLISHED)->latest('published_at');
    }

    public function scopeScheduled(Builder $query)
    {
        return $query->where('status', PostStatus::SCHEDULED)->latest('scheduled_for');
    }

    public function scopePending(Builder $query)
    {
        return $query->where('status', PostStatus::PENDING)->latest('created_at');
    }

    public function formattedPublishedDate()
    {
        return $this->published_at?->format('d M Y');
    }

    public function isScheduled()
    {
        return $this->status === PostStatus::SCHEDULED;
    }

    public function isStatusPublished()
    {
        return $this->status === PostStatus::PUBLISHED;
    }

    public function relatedPosts($take = 3)
    {
        return Post::where('_id', '!=', $this->_id) // escludi il post corrente
        ->whereIn('category_ids', $this->category_ids)           // supponendo che i post abbiano un campo array `category_ids`
        ->where('status', 'published')                    // equivalente di `published()`
        ->with('user')
            ->take($take)
            ->get();
    }

    protected function getFeaturePhotoAttribute()
    {
        return asset('storage/'.$this->cover_photo_path);
    }

    public static function getForm()
    {
        return [
            Section::make('Blog Details')
                ->schema([
                    Fieldset::make('Titles')
                        ->schema([
                            Select::make('category_ids')  // 'category_ids' è il campo che memorizza gli ID delle categorie
                                ->multiple()  // Permette di selezionare più categorie
                                ->preload()  // Carica tutte le categorie prima che l'utente inizi a cercare
                                ->searchable()  // Permette di cercare tra le categorie
                                ->createOptionForm(Category::getForm())  // Apre il form per creare una nuova categoria direttamente dal select
                                ->options(function () {
                                    // Carica tutte le categorie da MongoDB
                                    return Category::all()->pluck('name', '_id');  // 'name' è il campo visibile, '_id' è quello che verrà salvato
                                })
                                ->columnSpanFull(),  // Rende il campo a larghezza piena

                            TextInput::make('title')
                                ->live(true)
                                ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                                    'slug',
                                    Str::slug($state)
                                ))
                                ->required()
                                ->unique(config('filamentblog.tables.prefix').'posts', 'title', null, 'id')
                                ->maxLength(255),

                            TextInput::make('slug')
                                ->maxLength(255),

                            Textarea::make('sub_title')
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Select::make('tag_ids')  // 'tag_ids' è il campo che memorizza gli ID dei tag
                                ->multiple()  // Permette di selezionare più tag
                                ->preload()  // Carica tutti i tag prima che l'utente inizi a cercare
                                ->searchable()  // Permette di cercare tra i tag
                                ->createOptionForm(Tag::getForm())  // Apre il form per creare un nuovo tag direttamente dal select
                                ->options(function () {
                                    // Carica tutti i tag da MongoDB
                                    return Tag::all()->pluck('name', '_id');  // 'name' è il campo visibile, '_id' è quello che verrà salvato
                                })
                                ->columnSpanFull(),  // Rende il campo a larghezza piena
                        ]),
                    TiptapEditor::make('body')
                        ->profile('default')
                        ->disableFloatingMenus()
                        ->extraInputAttributes(['style' => 'max-height: 30rem; min-height: 24rem'])
                        ->required()
                        ->columnSpanFull(),
                    Fieldset::make('Feature Image')
                        ->schema([
                            FileUpload::make('cover_photo_path')
                                ->label('Cover Photo')
                                ->directory('/blog-feature-images')
                                ->hint('This cover image is used in your blog post as a feature image. Recommended image size 1200 X 628')
                                ->image()
                                ->preserveFilenames()
                                ->imageEditor()
                                ->maxSize(1024 * 5)
                                ->rules('dimensions:max_width=1920,max_height=1004')
                                ->required(),
                            TextInput::make('photo_alt_text')->required(),
                        ])->columns(1),

                    Fieldset::make('Status')
                        ->schema([

                            ToggleButtons::make('status')
                                ->live()
                                ->inline()
                                ->options(PostStatus::class)
                                ->required(),

                            DateTimePicker::make('scheduled_for')
                                ->visible(function ($get) {
                                    return $get('status') === PostStatus::SCHEDULED->value;
                                })
                                ->required(function ($get) {
                                    return $get('status') === PostStatus::SCHEDULED->value;
                                })
                                ->minDate(now()->addMinutes(5))
                                ->native(false),
                        ]),
                    Select::make(config('filamentblog.user.foreign_key'))
                        ->relationship('user', 'name')
                        ->nullable(false)
                        ->default(auth()->id()),

                ]),
        ];
    }

    public function getTable()
    {
        return config('filamentblog.tables.prefix') . 'posts';
    }
}
