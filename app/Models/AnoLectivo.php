<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class AnoLectivo extends Model
{
    use HasFactory;

    // Constantes de estado
    const ESTADO_ANTERIOR = 0;  // Anos lectivos passados
    const ESTADO_ACTUAL = 1;    // Ano lectivo actual (apenas um)
    const ESTADO_NOVO = 2;      // Anos lectivos futuros/novos

    protected $table = 'anos_lectivos';

    protected $fillable = [
        'nome',
        'estado',
        'data_inicio',
        'data_fim',
        'observacoes',
    ];

    protected $casts = [
        'estado' => 'integer',
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    // Scopes
    public function scopeActual($query)
    {
        return $query->where('estado', self::ESTADO_ACTUAL);
    }

    public function scopeAnteriores($query)
    {
        return $query->where('estado', self::ESTADO_ANTERIOR)->orderBy('nome', 'desc');
    }

    public function scopeNovos($query)
    {
        return $query->where('estado', self::ESTADO_NOVO)->orderBy('nome', 'asc');
    }

    // Relationships
    public function turmas()
    {
        return $this->hasMany(Turma::class);
    }

    public function alunos(): HasManyThrough
    {
        return $this->hasManyThrough(Aluno::class, Turma::class);
    }

    public function notas()
    {
        return $this->hasMany(Nota::class);
    }

    public function associacoes()
    {
        return $this->hasMany(ProfessorDisciplinaTurma::class);
    }

    public function directoresTurma()
    {
        return $this->hasMany(DirectorTurma::class);
    }

    public function coordenadoresCurso()
    {
        return $this->hasMany(CoordenadorCurso::class);
    }

    // Helper Methods
    public static function getActual()
    {
        return static::where('estado', self::ESTADO_ACTUAL)->first();
    }

    public static function setActual(string $nome): bool
    {
        DB::transaction(function () use ($nome) {
            // Obter o ano que será marcado como actual
            $anoNovo = static::where('nome', $nome)->first();

            if (!$anoNovo) {
                throw new \Exception("Ano lectivo {$nome} não encontrado.");
            }

            // Marcar o actual como anterior (1 -> 0)
            static::where('estado', self::ESTADO_ACTUAL)
                ->update(['estado' => self::ESTADO_ANTERIOR]);

            // Marcar o escolhido como actual
            // Se era novo (2), muda para actual (1)
            // Se era anterior (0), muda para actual (1)
            $anoNovo->update(['estado' => self::ESTADO_ACTUAL]);

            // Actualizar configuração
            Configuracao::set('ano_lectivo_actual', $nome, 'Ano lectivo actual do sistema');

            app(\App\Services\ConfiguracaoService::class)->clearCache();
        });

        return true;
    }

    public function isActual(): bool
    {
        return $this->estado === self::ESTADO_ACTUAL;
    }

    public function isNovo(): bool
    {
        return $this->estado === self::ESTADO_NOVO;
    }

    public function isAnterior(): bool
    {
        return $this->estado === self::ESTADO_ANTERIOR;
    }

    /**
     * Obter ID do ano actual
     */
    public static function getActualId(): ?int
    {
        return static::where('estado', self::ESTADO_ACTUAL)->value('id');
    }

    /**
     * Converter nome para ID
     */
    public static function getIdByNome(string $nome): ?int
    {
        return static::where('nome', $nome)->value('id');
    }

    /**
     * Garante que o ano lectivo existe (cria como anterior se em falta).
     * Necessário para gravar CAs históricas quando só o ano actual está registado.
     */
    public static function resolverIdPorNome(string $nome): int
    {
        return static::firstOrCreate(
            ['nome' => $nome],
            ['estado' => static::ESTADO_ANTERIOR]
        )->id;
    }

    /**
     * Converter ID para nome
     */
    public static function getNomeById(int $id): ?string
    {
        return static::where('id', $id)->value('nome');
    }

    /**
     * Obter todos os anos como array [id => nome]
     */
    public static function getParaSelect(): array
    {
        return static::orderBy('nome', 'desc')->pluck('nome', 'id')->toArray();
    }
}
