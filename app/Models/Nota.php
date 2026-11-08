<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Nota extends Model
{
    use HasFactory, LogsActivity;

    // Tipos de avaliação por trimestre
    const TIPO_MAC = 'MAC';  // Média de Avaliação Contínua
    const TIPO_PP = 'PP';    // Prova dos Professores
    const TIPO_PT = 'PT';    // Prova Trimestral
    const TIPO_MT = 'MT';    // Média Trimestral (calculada)

    // Novos tipos intermediários (Regras Oficiais)
    const TIPO_MFT2 = 'MFT2';  // Média Final dos 2 trimestres
    const TIPO_CF = 'CF';      // Classificação Final

    // Tipos anuais (Regras Oficiais)
    const TIPO_PG = 'PG';      // Prova Global
    const TIPO_CA10 = 'CA10';  // Classificação Anual 10ª
    const TIPO_CA11 = 'CA11';  // Classificação Anual 11ª
    const TIPO_CA12 = 'CA12';  // Classificação Anual 12ª
    const TIPO_CFD = 'CFD';    // Classificação Final da Disciplina

    // Tipos legados — mantidos apenas para compatibilidade com dados históricos
    /** @deprecated Usar TIPO_CFD ou TIPO_CA10/CA11/CA12 conforme a classe */
    const TIPO_MF = 'MF';       // Média Final (média dos 3 MTs) — FÓRMULA ANTIGA
    /** @deprecated Usar TIPO_PG (Prova Global) */
    const TIPO_EXAME = 'Exame'; // Exame Final — substituído por TIPO_PG
    const TIPO_RECURSO = 'Recurso';
    /** @deprecated Usar TIPO_CFD (Classificação Final da Disciplina) */
    const TIPO_FINAL = 'Final'; // Nota Final do Ano — FÓRMULA ANTIGA

    // Exames realizados em épocas especiais — Capítulo V do DE 424/25
    const TIPO_MELHORIA = 'Melhoria';         // Art.º 34.º — Exame de Melhoria de Nota
    const TIPO_EXTRAORDINARIO = 'Extraordinario'; // Art.º 32.º — Exame Extraordinário
    const TIPO_EQUIVALENCIA = 'Equivalencia'; // Art.º 33.º — Exame de Equivalência
    const TIPO_RECUPERACAO = 'Recuperacao';  // Art.º 30.º — Recuperação anual (substitui CFD)

    // Estados do conselho
    const ESTADO_PRE_CONSELHO = 'pre_conselho';
    const ESTADO_POS_CONSELHO = 'pos_conselho';

    protected $fillable = [
        'aluno_id',
        'disciplina_id',
        'turma_id',
        'professor_id',
        'tipo',
        'valor',
        'ano_lectivo',
        'ano_lectivo_id',
        'trimestre',
        'observacoes',
        'estado_conselho',
        'nota_origem_id',
        'archived_at',
        'archived_reason',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'ano_lectivo' => 'string',
            'trimestre' => 'integer',
            'estado_conselho' => 'string',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Mutator: Sincronizar ano_lectivo quando ano_lectivo_id é definido
     */
    protected function anoLectivoId(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                // Quando ID é definido, preencher string automaticamente
                if ($value && !isset($this->attributes['ano_lectivo'])) {
                    $this->attributes['ano_lectivo'] = AnoLectivo::getNomeById($value);
                }
                return $value;
            }
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['aluno_id', 'disciplina_id', 'tipo', 'valor', 'ano_lectivo', 'trimestre'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    /**
     * Relacionamento com a nota original (para notas pós-conselho)
     */
    public function notaOrigem(): BelongsTo
    {
        return $this->belongsTo(Nota::class, 'nota_origem_id');
    }

    /**
     * Relacionamento com a nota ajustada (para notas pré-conselho que foram ajustadas)
     */
    public function notaAjustada()
    {
        return $this->hasOne(Nota::class, 'nota_origem_id');
    }

    /**
     * Ajuste de conselho onde esta nota é a versão pré-conselho
     */
    public function ajusteConselhoComoPre()
    {
        return $this->hasOne(AjusteConselho::class, 'nota_pre_id');
    }

    /**
     * Ajuste de conselho onde esta nota é a versão pós-conselho
     */
    public function ajusteConselhoComoPos()
    {
        return $this->hasOne(AjusteConselho::class, 'nota_pos_id');
    }

    /**
     * Relacionamento com o utilizador que arquivou a nota
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Arquiva a nota com motivo e responsável
     */
    public function archive(string $reason, ?int $userId = null): bool
    {
        $this->archived_at = now();
        $this->archived_reason = $reason;
        $this->archived_by = $userId ?? auth()->id();

        $saved = $this->save();

        if ($saved) {
            activity()
                ->performedOn($this)
                ->causedBy($this->archived_by)
                ->withProperties([
                    'reason' => $reason,
                    'aluno_id' => $this->aluno_id,
                    'disciplina_id' => $this->disciplina_id,
                    'tipo' => $this->tipo,
                    'valor' => $this->valor,
                ])
                ->log('Nota arquivada');
        }

        return $saved;
    }

    /**
     * Restaura uma nota arquivada
     */
    public function restore(): bool
    {
        if (!$this->isArchived()) {
            return false;
        }

        $this->archived_at = null;
        $this->archived_reason = null;
        $this->archived_by = null;

        $saved = $this->save();

        if ($saved) {
            activity()
                ->performedOn($this)
                ->causedBy(auth()->id())
                ->withProperties([
                    'aluno_id' => $this->aluno_id,
                    'disciplina_id' => $this->disciplina_id,
                    'tipo' => $this->tipo,
                    'valor' => $this->valor,
                ])
                ->log('Nota restaurada');
        }

        return $saved;
    }

    /**
     * Verifica se a nota está arquivada
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Verifica se é nota pré-conselho
     */
    public function isPreConselho(): bool
    {
        return $this->estado_conselho === self::ESTADO_PRE_CONSELHO;
    }

    /**
     * Verifica se é nota pós-conselho
     */
    public function isPosConselho(): bool
    {
        return $this->estado_conselho === self::ESTADO_POS_CONSELHO;
    }

    /**
     * Verifica se esta nota pré-conselho foi ajustada
     */
    public function foiAjustada(): bool
    {
        return $this->isPreConselho() && $this->notaAjustada()->exists();
    }

    /**
     * Retorna o valor efetivo (pós-conselho se existir, senão pré-conselho)
     */
    public function getValorEfetivo(): float
    {
        if ($this->isPreConselho() && $this->notaAjustada) {
            return $this->notaAjustada->valor;
        }
        return $this->valor;
    }

    public function scopeAnoLectivo($query, $ano)
    {
        return $query->where('ano_lectivo', $ano);
    }

    public function scopeTrimestre($query, $trimestre)
    {
        return $query->where('trimestre', $trimestre);
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para filtrar apenas notas pré-conselho
     */
    public function scopePreConselho($query)
    {
        return $query->where('estado_conselho', self::ESTADO_PRE_CONSELHO);
    }

    /**
     * Pré-conselho ou legado (estado_conselho ainda null na BD).
     */
    public function scopePreConselhoLancamento($query)
    {
        return $query->where(function ($q) {
            $q->where('estado_conselho', self::ESTADO_PRE_CONSELHO)
                ->orWhereNull('estado_conselho')
                ->orWhere('estado_conselho', '');
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, self>  $notas
     */
    public static function agruparPorAlunoETipoEfectivo(\Illuminate\Support\Collection $notas): \Illuminate\Support\Collection
    {
        return $notas
            ->groupBy('aluno_id')
            ->map(fn ($notasAluno) => $notasAluno
                ->groupBy('tipo')
                ->map(fn ($grupo) => self::seleccionarNotaEfectiva($grupo))
            );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, self>  $notas
     */
    public static function seleccionarNotaEfectiva(\Illuminate\Support\Collection $notas): ?self
    {
        if ($notas->isEmpty()) {
            return null;
        }

        return $notas->firstWhere('estado_conselho', self::ESTADO_POS_CONSELHO)
            ?? $notas->firstWhere('estado_conselho', self::ESTADO_PRE_CONSELHO)
            ?? $notas->firstWhere(fn (self $nota) => $nota->estado_conselho === null);
    }

    /**
     * Selecciona a nota conforme o momento da pauta (pré ou pós conselho).
     *
     * @param  \Illuminate\Support\Collection<int, self>  $notas
     */
    public static function seleccionarNotaPorMomento(
        \Illuminate\Support\Collection $notas,
        string $momentoConselho,
    ): ?self {
        if ($notas->isEmpty()) {
            return null;
        }

        if (\App\Support\PautaMomentoConselho::isPre($momentoConselho)) {
            return $notas->firstWhere('estado_conselho', self::ESTADO_PRE_CONSELHO)
                ?? $notas->firstWhere(fn (self $nota) => $nota->estado_conselho === null || $nota->estado_conselho === '');
        }

        return self::seleccionarNotaEfectiva($notas);
    }

    /**
     * Scope para filtrar apenas notas pós-conselho
     */
    public function scopePosConselho($query)
    {
        return $query->where('estado_conselho', self::ESTADO_POS_CONSELHO);
    }

    /**
     * Scope para filtrar apenas notas ativas (não arquivadas)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope para filtrar apenas notas arquivadas
     */
    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Tipos de nota para lançamento trimestral (activos, para uso em formulários).
     */
    public static function getTiposTrimestrais(): array
    {
        return [
            self::TIPO_MAC => 'Média de Avaliação Contínua (MAC)',
            self::TIPO_PP => 'Prova dos Professores (PP)',
            self::TIPO_PT => 'Prova Trimestral (PT)',
        ];
    }

    /**
     * Tipos de nota activos — para uso em selects e formulários.
     *
     * Não inclui tipos legados (TIPO_MF, TIPO_EXAME, TIPO_FINAL) que existem
     * apenas para compatibilidade com dados históricos anteriores à reestruturação
     * do sistema de avaliação para o modelo MFT2 / CF / CFD.
     */
    public static function getTiposActivos(): array
    {
        return [
            self::TIPO_MAC  => 'Média de Avaliação Contínua (MAC)',
            self::TIPO_PP   => 'Prova dos Professores (PP)',
            self::TIPO_PT   => 'Prova Trimestral (PT)',
            self::TIPO_MT   => 'Média Trimestral',
            self::TIPO_MFT2 => 'Média Final dos 2 Trimestres',
            self::TIPO_CF   => 'Classificação Final',
            self::TIPO_PG   => 'Prova Global',
            self::TIPO_CA10 => 'Classificação Anual 10ª',
            self::TIPO_CA11 => 'Classificação Anual 11ª',
            self::TIPO_CA12 => 'Classificação Anual 12ª',
            self::TIPO_CFD  => 'Classificação Final da Disciplina',
            self::TIPO_RECUPERACAO => 'Exame de Recuperação',
            self::TIPO_RECURSO => 'Exame de Recurso',
        ];
    }

    /**
     * Rótulos para o formulário «Lançar Notas» (professores).
     */
    public static function rotulosLancamento(): array
    {
        return [
            self::TIPO_MAC => 'Média de Avaliação Contínua (MAC)',
            self::TIPO_PP => 'Prova dos Professores (PP)',
            self::TIPO_PT => 'Prova Trimestral (PT)',
            self::TIPO_PG => 'Prova Global (PG)',
            self::TIPO_RECUPERACAO => 'Exame de Recuperação',
            self::TIPO_RECURSO => 'Exame de Recurso',
        ];
    }

    public static function isTipoExameEspecial(string $tipo): bool
    {
        return in_array($tipo, [self::TIPO_RECUPERACAO, self::TIPO_RECURSO], true);
    }

    /**
     * Tipos de nota calculados/finais (inclui legados para compatibilidade).
     *
     * @deprecated Usar getTiposActivos() em novos formulários. Este método inclui
     *             TIPO_MF, TIPO_EXAME e TIPO_FINAL que são tipos da fórmula antiga
     *             e não devem ser seleccionados em novos lançamentos.
     */
    public static function getTiposFinais(): array
    {
        return [
            self::TIPO_MT   => 'Média Trimestral',
            self::TIPO_MFT2 => 'Média Final dos 2 Trimestres',
            self::TIPO_CF   => 'Classificação Final',
            self::TIPO_PG   => 'Prova Global',
            self::TIPO_CA10 => 'Classificação Anual 10ª',
            self::TIPO_CA11 => 'Classificação Anual 11ª',
            self::TIPO_CA12 => 'Classificação Anual 12ª',
            self::TIPO_CFD  => 'Classificação Final da Disciplina',
            // Tipos legados — mantidos para exibição de dados históricos
            self::TIPO_MF    => 'Média Final (legado)',
            self::TIPO_EXAME => 'Exame (legado)',
            self::TIPO_RECURSO => 'Recurso',
            self::TIPO_FINAL => 'Nota Final (legado)',
        ];
    }

    /**
     * Todos os tipos de nota (activos + legados).
     *
     * @deprecated Usar getTiposActivos() em novos formulários. Este método retorna
     *             todos os tipos incluindo os legados (TIPO_MF, TIPO_EXAME, TIPO_FINAL)
     *             e destina-se apenas a exibição de dados históricos e auditoria.
     */
    public static function getTipos(): array
    {
        return array_merge(self::getTiposActivos(), [
            // Tipos legados — apenas para exibição de dados históricos
            self::TIPO_MF    => 'Média Final (legado)',
            self::TIPO_EXAME => 'Exame (legado)',
            self::TIPO_FINAL => 'Nota Final (legado)',
        ]);
    }

    /**
     * Calcular média trimestral (MT)
     * Fórmula: MT = (MAC + PP + PT) / 3
     */
    public static function calcularMediaTrimestral(float $mac, float $pp, float $pt): float
    {
        return (float) round(($mac + $pp + $pt) / 3);
    }

    /**
     * Calcular média final pela fórmula antiga (LEGADO).
     *
     * @deprecated Este método usa a fórmula MF = (MT1 + MT2 + MT3) / 3, que foi
     *             substituída pelo modelo de avaliação actual:
     *
     *             MFT2 = (MT1 + MT2) / 2
     *             CF   = média(MFT2, MT3)
     *             CFD  = CF ou CA10/CA11/CA12 conforme a classe
     *
     *             Para novos cálculos, usar PautaService::calcularEGuardarNotaFinal()
     *             e PautaService::calcularEGuardarMediaTrimestral().
     *
     *             Mantido apenas para compatibilidade com dados históricos e
     *             eventuais recálculos de anos lectivos anteriores à migração.
     */
    public static function calcularMediaFinal(float $mt1, float $mt2, float $mt3): float
    {
        return (float) round(($mt1 + $mt2 + $mt3) / 3);
    }

    /**
     * Calcular nota final com exame
     * Fórmula: Final = (MF * 0.6) + (Exame * 0.4) ou conforme configuração
     */
    public static function calcularNotaFinalComExame(float $mf, float $exame, float $pesoMf = 0.6): float
    {
        $pesoExame = 1 - $pesoMf;
        return (float) round(($mf * $pesoMf) + ($exame * $pesoExame));
    }

    public function isPositiva(): bool
    {
        return $this->valor !== null && $this->valor >= 10;
    }

    public function isNegativa(): bool
    {
        return $this->valor !== null && $this->valor < 10;
    }

    /**
     * Obter descrição do tipo de nota
     */
    public function getTipoDescricao(): string
    {
        return self::getTipos()[$this->tipo] ?? $this->tipo;
    }

    /**
     * Obter cor do badge conforme a nota
     */
    public function getBadgeColor(): string
    {
        if ($this->valor === null) {
            return 'secondary';
        }

        if ($this->valor >= 14) {
            return 'success';
        } elseif ($this->valor >= 10) {
            return 'primary';
        } else {
            return 'danger';
        }
    }

    /**
     * Obtém o valor da Prova Global (PG).
     *
     * No regime politécnico, professores que lançaram PT no 3.º trimestre
     * antes da migração PT3 → PG continuam visíveis via fallback (só quando
     * ainda não existe PG oficial).
     */
    public static function valorProvaGlobal(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $anoLectivoId,
        bool $fallbackPt3 = true,
    ): ?float {
        $anoActualId = Configuracao::getAnoLectivoActualId();
        $incluirArquivadas = $anoActualId !== null && (int) $anoLectivoId !== (int) $anoActualId;

        $pg = static::query()
            ->where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereNull('trimestre')
            ->where('tipo', self::TIPO_PG)
            ->when(! $incluirArquivadas, fn ($q) => $q->whereNull('archived_at'))
            ->orderByRaw('archived_at IS NULL DESC')
            ->orderByRaw('CASE WHEN estado_conselho = ? THEN 0 ELSE 1 END', [self::ESTADO_POS_CONSELHO])
            ->first();

        if ($pg !== null) {
            return (float) $pg->valor;
        }

        if (! $fallbackPt3) {
            return null;
        }

        $pt3 = static::query()
            ->where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('trimestre', 3)
            ->where('tipo', self::TIPO_PT)
            ->when(! $incluirArquivadas, fn ($q) => $q->whereNull('archived_at'))
            ->orderByRaw('archived_at IS NULL DESC')
            ->orderByRaw('CASE WHEN estado_conselho = ? THEN 0 ELSE 1 END', [self::ESTADO_POS_CONSELHO])
            ->first();

        return $pt3 !== null ? (float) $pt3->valor : null;
    }

    /**
     * Obter qualificação da nota
     */
    public function getQualificacao(): string
    {
        if ($this->valor === null) {
            return 'Sem nota';
        }

        if ($this->valor >= 18) {
            return 'Excelente';
        } elseif ($this->valor >= 16) {
            return 'Muito Bom';
        } elseif ($this->valor >= 14) {
            return 'Bom';
        } elseif ($this->valor >= 10) {
            return 'Suficiente';
        } else {
            return 'Insuficiente';
        }
    }
}
