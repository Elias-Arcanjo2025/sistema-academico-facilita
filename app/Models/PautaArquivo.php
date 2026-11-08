<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PautaArquivo extends Model
{
    use HasFactory, LogsActivity;

    // Constantes para tipos de pauta
    const TIPO_MINI_PAUTA = 'mini_pauta';
    const TIPO_FINAL_DISCIPLINA = 'final_disciplina';
    const TIPO_FINAL_TURMA = 'final_turma';
    const TIPO_COMPLETA = 'completa';
    const TIPO_OUTRA = 'outra';

    // Constantes para origem do ficheiro
    const ORIGEM_SISTEMA = 'sistema';
    const ORIGEM_EXTERNA = 'externa';

    // Constantes para trimestres
    const TRIMESTRE_ANUAL = 0;
    const TRIMESTRE_1 = 1;
    const TRIMESTRE_2 = 2;
    const TRIMESTRE_3 = 3;

    protected $table = 'pauta_arquivos';

    protected $fillable = [
        'curso_id',
        'turma_id',
        'turma_manual',
        'sala_id',
        'sala_manual',
        'disciplina_id',
        'disciplina_manual',
        'uploaded_by',
        'ano_lectivo',
        'trimestre',
        'versao',
        'tipo_pauta',
        'origem',
        'nome_original',
        'nome_armazenado',
        'caminho_arquivo',
        'tamanho_bytes',
        'mime_type',
        'hash_arquivo',
        'descricao',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'tamanho_bytes' => 'integer',
            'trimestre' => 'integer',
            'versao' => 'integer',
        ];
    }

    /**
     * Configuração do Activity Log
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'curso_id',
                'turma_id',
                'ano_lectivo',
                'trimestre',
                'versao',
                'tipo_pauta',
                'origem',
                'nome_original',
                'activo'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ==================== RELAÇÕES ====================

    /**
     * Curso ao qual a pauta pertence (obrigatório)
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Turma específica (opcional - para pautas externas pode ser null)
     */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    /**
     * Sala onde ocorreu (opcional)
     */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    /**
     * Disciplina específica (opcional - null para pauta completa de turma)
     */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    /**
     * Utilizador que fez upload do ficheiro
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ==================== SCOPES ====================

    /**
     * Scope para filtrar apenas arquivos activos
     */
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para filtrar por ano lectivo
     */
    public function scopeAnoLectivo($query, $ano)
    {
        return $query->where('ano_lectivo', $ano);
    }

    /**
     * Scope para filtrar por curso
     */
    public function scopeCurso($query, $cursoId)
    {
        return $query->where('curso_id', $cursoId);
    }

    /**
     * Scope para filtrar por turma
     */
    public function scopeTurma($query, $turmaId)
    {
        return $query->where('turma_id', $turmaId);
    }

    /**
     * Scope para filtrar por tipo de pauta
     */
    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo_pauta', $tipo);
    }

    /**
     * Scope para filtrar por trimestre
     */
    public function scopeTrimestre($query, $trimestre)
    {
        return $query->where('trimestre', $trimestre);
    }

    // ==================== ACESSORES ====================

    /**
     * Retorna o tamanho do ficheiro formatado
     */
    public function getTamanhoFormatadoAttribute(): string
    {
        $bytes = $this->tamanho_bytes;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Retorna a URL de download do ficheiro
     */
    public function getUrlDownloadAttribute(): string
    {
        return route('pauta-arquivos.download', $this->id);
    }

    /**
     * Verifica se o ficheiro existe no storage
     */
    public function getExisteAttribute(): bool
    {
        return Storage::disk('local')->exists($this->caminho_arquivo);
    }

    /**
     * Retorna o nome do tipo de pauta formatado
     */
    public function getNomeTipoAttribute(): string
    {
        return self::getTiposPauta()[$this->tipo_pauta] ?? 'Desconhecido';
    }

    /**
     * Retorna o nome da origem formatado
     */
    public function getNomeOrigemAttribute(): string
    {
        return self::getOrigensArquivo()[$this->origem] ?? 'Desconhecido';
    }

    /**
     * Retorna o nome do trimestre formatado
     */
    public function getNomeTrimestreAttribute(): string
    {
        return self::getTrimestres()[$this->trimestre] ?? 'Desconhecido';
    }

    /**
     * Retorna o número da versão formatado (ex: v1, v2)
     */
    public function getVersaoFormatadaAttribute(): string
    {
        return 'v' . $this->versao;
    }

    /**
     * Retorna todas as versões anteriores desta pauta
     */
    public function getVersoesAnterioresAttribute()
    {
        return self::where('curso_id', $this->curso_id)
            ->where('ano_lectivo', $this->ano_lectivo)
            ->where('trimestre', $this->trimestre)
            ->when($this->disciplina_id, function ($query) {
                return $query->where('disciplina_id', $this->disciplina_id);
            }, function ($query) {
                return $query->whereNull('disciplina_id');
            })
            ->where('id', '!=', $this->id)
            ->orderBy('versao', 'desc')
            ->get();
    }

    // ==================== MÉTODOS AUXILIARES ====================

    /**
     * Retorna array com todos os tipos de pauta disponíveis
     */
    public static function getTiposPauta(): array
    {
        return [
            self::TIPO_MINI_PAUTA => 'Mini-Pauta',
            self::TIPO_FINAL_DISCIPLINA => 'Pauta Final de Disciplina',
            self::TIPO_FINAL_TURMA => 'Pauta Final de Turma',
            self::TIPO_COMPLETA => 'Pauta Completa',
            self::TIPO_OUTRA => 'Outra',
        ];
    }

    /**
     * Retorna array com todas as origens de arquivo disponíveis
     */
    public static function getOrigensArquivo(): array
    {
        return [
            self::ORIGEM_SISTEMA => 'Gerado pelo Sistema',
            self::ORIGEM_EXTERNA => 'Arquivo Externo',
        ];
    }

    /**
     * Retorna array com todos os trimestres disponíveis
     */
    public static function getTrimestres(): array
    {
        return [
            self::TRIMESTRE_ANUAL => 'Anual',
            self::TRIMESTRE_1 => '1º Trimestre',
            self::TRIMESTRE_2 => '2º Trimestre',
            self::TRIMESTRE_3 => '3º Trimestre',
        ];
    }

    /**
     * Retorna a próxima versão disponível para uma pauta específica
     */
    public static function getProximaVersao(
        int $cursoId,
        string $anoLectivo,
        int $trimestre,
        ?int $disciplinaId = null
    ): int {
        $query = self::where('curso_id', $cursoId)
            ->where('ano_lectivo', $anoLectivo)
            ->where('trimestre', $trimestre);

        if ($disciplinaId) {
            $query->where('disciplina_id', $disciplinaId);
        } else {
            $query->whereNull('disciplina_id');
        }

        $ultimaVersao = $query->max('versao');

        return $ultimaVersao ? $ultimaVersao + 1 : 1;
    }

    /**
     * Retorna todas as versões de uma pauta (incluindo a atual)
     */
    public function getTodasVersoes()
    {
        return self::where('curso_id', $this->curso_id)
            ->where('ano_lectivo', $this->ano_lectivo)
            ->where('trimestre', $this->trimestre)
            ->when($this->disciplina_id, function ($query) {
                return $query->where('disciplina_id', $this->disciplina_id);
            }, function ($query) {
                return $query->whereNull('disciplina_id');
            })
            ->orderBy('versao', 'desc')
            ->get();
    }
}
