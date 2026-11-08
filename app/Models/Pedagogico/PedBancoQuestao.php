<?php

namespace App\Models\Pedagogico;

use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedBancoQuestao extends Model
{
    use SoftDeletes;

    public const TIPO_ESCOLHA = 'escolha_multipla';

    public const TIPO_VF = 'verdadeiro_falso';

    public const TIPO_CURTA = 'resposta_curta';

    public const TIPO_DESENV = 'desenvolvimento';

    public const DIFF_FACIL = 'facil';

    public const DIFF_MEDIA = 'media';

    public const DIFF_DIFICIL = 'dificil';

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_ARQUIVADA = 'arquivada';

    protected $table = 'ped_banco_questoes';

    protected $fillable = [
        'disciplina_id',
        'criado_por',
        'tipo',
        'enunciado',
        'opcoes_json',
        'resposta_json',
        'cotacao',
        'dificuldade',
        'tags',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'opcoes_json' => 'array',
            'resposta_json' => 'array',
            'cotacao' => 'decimal:2',
        ];
    }

    public static function tipos(): array
    {
        return [
            self::TIPO_ESCOLHA => 'Escolha múltipla',
            self::TIPO_VF => 'Verdadeiro / Falso',
            self::TIPO_CURTA => 'Resposta curta',
            self::TIPO_DESENV => 'Desenvolvimento',
        ];
    }

    public static function dificuldades(): array
    {
        return [
            self::DIFF_FACIL => 'Fácil',
            self::DIFF_MEDIA => 'Média',
            self::DIFF_DIFICIL => 'Difícil',
        ];
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', self::ESTADO_ACTIVA);
    }

    /**
     * Formato compatível com conteudo_json de PedProva.
     *
     * @return array<string, mixed>
     */
    public function paraConteudoProva(int $numero = 1): array
    {
        $item = [
            'numero' => $numero,
            'enunciado' => $this->enunciado,
            'tipo' => $this->tipo,
            'cotacao' => (float) $this->cotacao,
            'dificuldade' => $this->dificuldade,
            'banco_questao_id' => $this->id,
        ];

        if (is_array($this->opcoes_json) && $this->opcoes_json !== []) {
            $item['opcoes'] = $this->opcoes_json;
        }

        if (is_array($this->resposta_json) && $this->resposta_json !== []) {
            $item['resposta'] = $this->resposta_json['resposta'] ?? $this->resposta_json;
        }

        return $item;
    }
}
