<?php

namespace App\Models\Pedagogico;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PedCorrecao extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'ped_correcoes';

    protected $fillable = [
        'prova_id',
        'aluno_id',
        'professor_id',
        'ficheiro_path',
        'ocr_provider',
        'ocr_texto',
        'ocr_confianca_global',
        'sugestao_nota',
        'nota_confirmada',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'ocr_confianca_global' => 'decimal:4',
            'sugestao_nota' => 'decimal:2',
            'nota_confirmada' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function prova(): BelongsTo
    {
        return $this->belongsTo(PedProva::class, 'prova_id');
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedCorrecaoItem::class, 'correcao_id');
    }
}
