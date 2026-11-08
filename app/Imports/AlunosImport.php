<?php

namespace App\Imports;

use App\Models\Aluno;
use App\Models\Turma;
use App\Services\MatriculaService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AlunosImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure, WithLimit, WithEvents
{
    use SkipsErrors, SkipsFailures;

    protected ?int $turmaId;
    protected int $importedCount = 0;
    protected array $numeroOrdemCache = [];
    protected array $numeroProcessoCache = []; // [ano_lectivo_id => processos] — duplicatas no ficheiro
    protected array $turmasAfetadas = []; // 🔤 Turmas que precisam recálculo alfabético
    protected ?int $currentTurmaIdForValidation = null;

    /** @var list<array<string, mixed>> */
    protected array $filiacoesPendentes = [];

    // Limite máximo de linhas por importação (melhoria #2)
    public const MAX_ROWS = 500;

    public function __construct(?int $turmaId = null)
    {
        $this->turmaId = $turmaId;
    }

    /**
     * Define o limite de linhas que serão importadas
     * Implementa melhoria #2: Limite de 500 linhas
     */
    public function limit(): int
    {
        return self::MAX_ROWS;
    }

    /**
     * Prepara os dados antes da validação
     * Normaliza tipos de dados que o Excel pode enviar incorretamente
     */
    public function prepareForValidation($data, $index)
    {
        // Converter numero_processo para string (Excel pode enviar como número)
        if (isset($data['numero_processo'])) {
            $data['numero_processo'] = (string) $data['numero_processo'];
        }

        foreach (['pai_telefone', 'mae_telefone'] as $campoTelefone) {
            if (isset($data[$campoTelefone]) && is_numeric($data[$campoTelefone])) {
                $data[$campoTelefone] = (string) $data[$campoTelefone];
            }
        }

        // Normalizar data_nascimento
        if (isset($data['data_nascimento'])) {
            $data['data_nascimento'] = $this->normalizeDate($data['data_nascimento']);
        }

        $turmaId = $data['turma_id'] ?? $this->turmaId;
        $this->currentTurmaIdForValidation = is_numeric($turmaId) ? (int) $turmaId : null;

        return $data;
    }

    /**
     * Normaliza diferentes formatos de data
     */
    protected function normalizeDate($value)
    {
        // Se é null ou vazio, retornar como está
        if (empty($value)) {
            return $value;
        }

        try {
            // Se é um número (formato Excel serial date)
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            }

            // Se já é uma string de data, tentar parsear
            if (is_string($value)) {
                // Tentar formato DD/MM/YYYY
                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
                    return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
                }

                // Tentar formato DD-MM-YYYY
                if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $matches)) {
                    return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
                }

                // Se já está em formato Y-m-d, retornar como está
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return $value;
                }
            }

            // Tentar converter usando Carbon
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            // Se falhar, retornar valor original para que a validação capture o erro
            return $value;
        }
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Buscar turma_id se fornecido na planilha, senão usar o fornecido
        $turmaId = $row['turma_id'] ?? $this->turmaId;

        // Validar se turma foi especificada
        if (!$turmaId) {
            throw new \Exception("Turma não especificada. Forneça turma_id na planilha ou selecione uma turma padrão.");
        }

        // Validar se a turma existe
        if (!Turma::find($turmaId)) {
            throw new \Exception("Turma ID {$turmaId} não encontrada.");
        }

        // 🔤 ORDEM ALFABÉTICA: Deixar null, será recalculado após importação
        // Rastrear turma para recálculo posterior
        if (!in_array($turmaId, $this->turmasAfetadas)) {
            $this->turmasAfetadas[] = $turmaId;
        }

        $this->importedCount++;

        $this->filiacoesPendentes[] = [
            'numero_processo' => (string) $row['numero_processo'],
            'turma_id' => (int) $turmaId,
            'pai_nome' => $row['pai_nome'] ?? null,
            'pai_telefone' => $row['pai_telefone'] ?? null,
            'mae_nome' => $row['mae_nome'] ?? null,
            'mae_telefone' => $row['mae_telefone'] ?? null,
        ];

        return new Aluno([
            'numero_processo' => (string) $row['numero_processo'], // Garantir que é string
            'nome' => $row['nome'],
            'data_nascimento' => $row['data_nascimento'], // Já normalizada em prepareForValidation
            'genero' => strtoupper($row['genero']),
            'bi_numero' => $row['bi_numero'] ?? null,
            'turma_id' => $turmaId,
            'numero_ordem' => null, // Será calculado em ordem alfabética após importação
            'activo' => $row['activo'] ?? true,
            'atleta_alta_competicao' => (bool) ($row['atleta_alta_competicao'] ?? false),
            'faltou_exame_normal' => (bool) ($row['faltou_exame_normal'] ?? false),
            'educacao_especial' => (bool) ($row['educacao_especial'] ?? false),
            'necessidades_especificas' => $row['necessidades_especificas'] ?? null,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);
    }

    /**
     * Regras de validação para cada linha
     * Melhoria #4: Validação de data rigorosa
     */
    public function rules(): array
    {
        return [
            'numero_processo' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail): void {
                    $this->validarNumeroProcessoPorAnoLectivo($value, $this->currentTurmaIdForValidation, $fail);
                },
            ],
            'nome' => 'required|string|max:255',
            'data_nascimento' => [
                'required',
                'date',
                'before:today',       // Não aceita datas futuras
                'after:1900-01-01',   // Limite inferior razoável
            ],
            'genero' => 'required|in:M,F,m,f',
            'bi_numero' => 'nullable|string|max:50',
            'pai_nome' => 'nullable|string|max:255',
            'pai_telefone' => 'nullable|string|max:40',
            'mae_nome' => 'nullable|string|max:255',
            'mae_telefone' => 'nullable|string|max:40',
            'turma_id' => 'nullable|exists:turmas,id',
            'activo' => 'nullable|boolean',
            'atleta_alta_competicao' => 'nullable|boolean',
            'faltou_exame_normal' => 'nullable|boolean',
            'educacao_especial' => 'nullable|boolean',
            'necessidades_especificas' => 'nullable|string|max:2000',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                $this->processarFiliacoes();
            },
        ];
    }

    protected function processarFiliacoes(): void
    {
        if ($this->filiacoesPendentes === []) {
            return;
        }

        /** @var MatriculaService $matriculaService */
        $matriculaService = app(MatriculaService::class);

        foreach ($this->filiacoesPendentes as $item) {
            $aluno = Aluno::query()
                ->where('numero_processo', $item['numero_processo'])
                ->where('turma_id', $item['turma_id'])
                ->latest('id')
                ->first();

            if (! $aluno) {
                continue;
            }

            $matriculaService->vincularFiliacao($aluno, [
                'pai' => [
                    'nome' => $item['pai_nome'] ?? null,
                    'telefone' => $item['pai_telefone'] ?? null,
                ],
                'mae' => [
                    'nome' => $item['mae_nome'] ?? null,
                    'telefone' => $item['mae_telefone'] ?? null,
                ],
                'encarregado' => ['nome' => null],
            ]);
        }
    }

    /**
     * Valida unicidade do número de processo por ano lectivo (turma destino).
     */
    protected function validarNumeroProcessoPorAnoLectivo(string $value, ?int $turmaId, callable $fail): void
    {
        if (! $turmaId) {
            return;
        }

        $anoLectivoId = Turma::find($turmaId)?->ano_lectivo_id;
        $cacheKey = $anoLectivoId ?? 'sem_ano';

        if (! isset($this->numeroProcessoCache[$cacheKey])) {
            $this->numeroProcessoCache[$cacheKey] = [];
        }

        if (in_array($value, $this->numeroProcessoCache[$cacheKey], true)) {
            $fail("O número de processo {$value} está duplicado no ficheiro Excel (mesmo ano lectivo).");

            return;
        }

        $this->numeroProcessoCache[$cacheKey][] = $value;

        if (! $anoLectivoId) {
            return;
        }

        $duplicado = Aluno::where('numero_processo', $value)
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->exists();

        if ($duplicado) {
            $fail("O número de processo {$value} já está cadastrado neste ano lectivo.");
        }
    }

    /**
     * Mensagens de erro personalizadas
     */
    public function customValidationMessages(): array
    {
        return [
            'numero_processo.required' => 'O número de processo é obrigatório.',
            'nome.required' => 'O nome é obrigatório.',
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'data_nascimento.date' => 'A data de nascimento deve ser uma data válida.',
            'data_nascimento.before' => 'A data de nascimento não pode ser uma data futura.',
            'data_nascimento.after' => 'A data de nascimento deve ser posterior a 01/01/1900.',
            'genero.required' => 'O gênero é obrigatório.',
            'genero.in' => 'O gênero deve ser M (Masculino) ou F (Feminino).',
            'turma_id.exists' => 'A turma especificada não existe.',
        ];
    }

    /**
     * Retorna o número de registros importados
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * 🔤 Retorna IDs das turmas afetadas para recálculo alfabético
     */
    public function getTurmasAfetadas(): array
    {
        return $this->turmasAfetadas;
    }
}
