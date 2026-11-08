<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlunoDocumento extends Model
{
    protected $table = 'aluno_documentos';

    public const TIPO_BI = 'bi';
    public const TIPO_CERTIDAO = 'certidao';
    public const TIPO_FOTO = 'foto';
    public const TIPO_OUTRO = 'outro';

    protected $fillable = [
        'aluno_id',
        'tipo',
        'titulo',
        'path',
        'mime',
        'tamanho',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'tamanho' => 'integer',
        ];
    }

    public static function tipos(): array
    {
        return [
            self::TIPO_BI => 'Bilhete de Identidade',
            self::TIPO_CERTIDAO => 'Certidão',
            self::TIPO_FOTO => 'Fotografia',
            self::TIPO_OUTRO => 'Outro',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
