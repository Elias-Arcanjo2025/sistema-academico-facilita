<?php

namespace App\Imports;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Nota;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class NotasImport implements ToCollection, WithHeadingRow
{
    protected $turmaId;
    protected $disciplinas;
    protected $trimestre;
    protected $anoLectivoId;
    protected $professorId;

    public $criadas = 0;
    public $atualizadas = 0;
    public $erros = [];

    public function __construct($turmaId, $disciplinas, $trimestre, $anoLectivoId, $professorId)
    {
        $this->turmaId = $turmaId;
        $this->disciplinas = $disciplinas;
        $this->trimestre = $trimestre;
        $this->anoLectivoId = $anoLectivoId;
        $this->professorId = $professorId;
    }

    public function collection(Collection $rows)
    {
        $anoLectivoNome = AnoLectivo::getNomeById($this->anoLectivoId);

        // Mapear códigos de disciplinas para IDs
        $disciplinaMap = [];
        foreach ($this->disciplinas as $disciplina) {
            $disciplinaMap[$disciplina->codigo] = $disciplina->id;
        }

        foreach ($rows as $index => $row) {
            $linha = $index + 2; // +2 porque: +1 (índice 0) +1 (linha de cabeçalho)

            try {
                // Validar número de ordem
                $numeroOrdem = $row['no'] ?? $row['numero'] ?? null;

                if (!$numeroOrdem) {
                    $this->erros[] = "Linha {$linha}: Número de ordem não encontrado";
                    continue;
                }

                // Buscar aluno pelo número de ordem e turma
                $aluno = Aluno::where('turma_id', $this->turmaId)
                    ->where('numero_ordem', $numeroOrdem)
                    ->activo()
                    ->first();

                if (!$aluno) {
                    $this->erros[] = "Linha {$linha}: Aluno com número {$numeroOrdem} não encontrado na turma";
                    continue;
                }

                // Processar notas para cada disciplina
                foreach ($this->disciplinas as $disciplina) {
                    $codigo = $disciplina->codigo;

                    // Buscar colunas no formato: "CODIGO - TIPO" (ex: "MAT - MAC")
                    $macKey = strtolower($codigo . '_mac');
                    $ppKey = strtolower($codigo . '_pp');
                    $ptKey = strtolower($codigo . '_pt');

                    // Processar MAC
                    if (isset($row[$macKey]) && $row[$macKey] !== '' && $row[$macKey] !== null) {
                        $this->processarNota(
                            $aluno->id,
                            $disciplina->id,
                            Nota::TIPO_MAC,
                            $this->formatarValor($row[$macKey]),
                            $anoLectivoNome,
                            $linha,
                            $codigo . ' - MAC'
                        );
                    }

                    // Processar PP
                    if (isset($row[$ppKey]) && $row[$ppKey] !== '' && $row[$ppKey] !== null) {
                        $this->processarNota(
                            $aluno->id,
                            $disciplina->id,
                            Nota::TIPO_PP,
                            $this->formatarValor($row[$ppKey]),
                            $anoLectivoNome,
                            $linha,
                            $codigo . ' - PP'
                        );
                    }

                    // Processar PT
                    if (isset($row[$ptKey]) && $row[$ptKey] !== '' && $row[$ptKey] !== null) {
                        $this->processarNota(
                            $aluno->id,
                            $disciplina->id,
                            Nota::TIPO_PT,
                            $this->formatarValor($row[$ptKey]),
                            $anoLectivoNome,
                            $linha,
                            $codigo . ' - PT'
                        );
                    }
                }

            } catch (\Exception $e) {
                $this->erros[] = "Linha {$linha}: Erro ao processar - " . $e->getMessage();
            }
        }
    }

    protected function processarNota($alunoId, $disciplinaId, $tipo, $valor, $anoLectivoNome, $linha, $campo)
    {
        // Validar valor
        if ($valor < 0 || $valor > 20) {
            $this->erros[] = "Linha {$linha} ({$campo}): Nota deve estar entre 0 e 20 (recebido: {$valor})";
            return;
        }

        try {
            $nota = Nota::updateOrCreate(
                [
                    'aluno_id' => $alunoId,
                    'disciplina_id' => $disciplinaId,
                    'turma_id' => $this->turmaId,
                    'tipo' => $tipo,
                    'trimestre' => $this->trimestre,
                    'ano_lectivo_id' => $this->anoLectivoId,
                ],
                [
                    'valor' => $valor,
                    'professor_id' => $this->professorId,
                    'ano_lectivo' => $anoLectivoNome,
                ]
            );

            $nota->wasRecentlyCreated ? $this->criadas++ : $this->atualizadas++;
        } catch (\Exception $e) {
            $this->erros[] = "Linha {$linha} ({$campo}): Erro ao salvar nota - " . $e->getMessage();
        }
    }

    protected function formatarValor($valor)
    {
        // Substituir vírgula por ponto para decimais
        $valor = str_replace(',', '.', $valor);

        // Remover espaços
        $valor = trim($valor);

        // Converter para float
        return (float) $valor;
    }

    public function headingRow(): int
    {
        return 1;
    }
}
