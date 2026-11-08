<?php

namespace App\Services;

use App\Models\CalendarioProva;
use App\Models\CoordenadorCurso;
use App\Models\DirectorTurma;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\TurmaCalendarioProvaConfig;
use App\Models\TurmaHorario;
use App\Models\TurmaHorarioConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnoLectivoTransferService
{
    private int $skippedTurmas = 0;

    /**
     * Valida a transferência antes de executar
     */
    public function validateTransfer(string $anoOrigem, string $anoDestino): array
    {
        $errors = [];

        // Validar formato
        if (!preg_match('/^\d{4}-\d{4}$/', $anoOrigem)) {
            $errors[] = 'Ano de origem deve estar no formato YYYY-YYYY (ex: 2024-2025)';
        }

        if (!preg_match('/^\d{4}-\d{4}$/', $anoDestino)) {
            $errors[] = 'Ano de destino deve estar no formato YYYY-YYYY (ex: 2025-2026)';
        }

        // Não pode ser igual
        if ($anoOrigem === $anoDestino) {
            $errors[] = 'Ano de origem e destino não podem ser iguais';
        }

        // Verificar se ano origem tem dados
        $anoOrigemId = \App\Models\AnoLectivo::getIdByNome($anoOrigem);
        if ($anoOrigemId) {
            $turmasOrigem = Turma::where('ano_lectivo_id', $anoOrigemId)->count();
            if ($turmasOrigem === 0) {
                $errors[] = "Não existem turmas no ano lectivo {$anoOrigem}";
            }
        } else {
            $errors[] = "Ano lectivo de origem {$anoOrigem} não encontrado no sistema";
        }

        // Verificar se ano destino existe e é um ano novo (estado = 2)
        $anoDestinoModel = \App\Models\AnoLectivo::where('nome', $anoDestino)->first();
        if (!$anoDestinoModel) {
            $errors[] = "Ano lectivo de destino {$anoDestino} não encontrado. Por favor, cadastre o ano antes de fazer a transferência.";
        } elseif (!$anoDestinoModel->isNovo()) {
            $errors[] = "O ano lectivo {$anoDestino} não está marcado como 'novo'. Apenas anos novos podem receber transferências.";
        }

        return $errors;
    }

    /**
     * Gera preview da transferência
     */
    public function previewTransfer(string $anoOrigem, string $anoDestino): array
    {
        $anoOrigemId = \App\Models\AnoLectivo::getIdByNome($anoOrigem);
        $anoDestinoId = \App\Models\AnoLectivo::getIdByNome($anoDestino);

        $turmasOrigem = Turma::with(['curso', 'classe', 'sala', 'turno'])
            ->where('ano_lectivo_id', $anoOrigemId)
            ->get();

        $turmasDestino = Turma::where('ano_lectivo_id', $anoDestinoId)->get();

        $associacoesOrigem = ProfessorDisciplinaTurma::where('ano_lectivo_id', $anoOrigemId)->count();
        $directoresOrigem = DirectorTurma::where('ano_lectivo_id', $anoOrigemId)->count();
        $coordenadoresOrigem = CoordenadorCurso::where('ano_lectivo_id', $anoOrigemId)->count();

        $preview = [
            'ano_origem' => $anoOrigem,
            'ano_destino' => $anoDestino,
            'stats' => [
                'turmas' => $turmasOrigem->count(),
                'associacoes' => $associacoesOrigem,
                'directores' => $directoresOrigem,
                'coordenadores' => $coordenadoresOrigem,
            ],
            'sample_turmas' => $turmasOrigem->take(10), // A view espera até 10 turmas
            'warnings' => [],
            'conflicts' => [],
        ];

        // Avisos
        if ($turmasDestino->count() > 0) {
            $preview['warnings'][] = "O ano destino {$anoDestino} já tem {$turmasDestino->count()} turma(s). Serão criadas novas turmas.";
        }

        // Verificar conflitos potenciais (turmas com mesmo código)
        $codigosOrigem = $turmasOrigem->pluck('codigo')->toArray();
        $codigosDestino = $turmasDestino->pluck('codigo')->toArray();
        $codigosConflito = array_intersect($codigosOrigem, $codigosDestino);

        if (!empty($codigosConflito)) {
            $preview['conflicts'][] = 'Algumas turmas já existem no ano destino com os mesmos códigos: ' . implode(', ', $codigosConflito);
        }

        return $preview;
    }

    /**
     * Executa a transferência completa
     */
    public function executeTransfer(
        string $anoOrigem,
        string $anoDestino,
        bool $transferirAssociacoes = true,
        bool $transferirDirectores = true,
        bool $transferirCoordenadores = true,
        bool $transferirHorarios = false,
        bool $transferirCalendario = false
    ): array {
        // Garantir que os anos existem na tabela centralizada
        $anoOrigemModel = \App\Models\AnoLectivo::firstOrCreate(
            ['nome' => $anoOrigem],
            ['estado' => \App\Models\AnoLectivo::ESTADO_ANTERIOR]
        );
        $anoDestinoModel = \App\Models\AnoLectivo::firstOrCreate(
            ['nome' => $anoDestino],
            ['estado' => \App\Models\AnoLectivo::ESTADO_NOVO]
        );

        $stats = [
            'turmas' => 0,
            'associacoes' => 0,
            'directores' => 0,
            'coordenadores' => 0,
            'horarios' => 0,
            'calendario_provas' => 0,
            'turmas_criadas' => [],
        ];

        DB::beginTransaction();

        try {
            $turmaMapping = $this->transferTurmas($anoOrigem, $anoDestino, $anoOrigemModel->id, $anoDestinoModel->id);
            $stats['turmas'] = count($turmaMapping) - $this->skippedTurmas;
            $stats['skipped'] = $this->skippedTurmas;
            $stats['turmas_criadas'] = array_values($turmaMapping);

            // 2. Transferir associações (opcional)
            if ($transferirAssociacoes) {
                $stats['associacoes'] = $this->transferAssociacoes($anoOrigem, $anoDestino, $turmaMapping, $anoOrigemModel->id, $anoDestinoModel->id);
            }

            // 3. Transferir directores (opcional)
            if ($transferirDirectores) {
                $stats['directores'] = $this->transferDirectores($anoOrigem, $anoDestino, $turmaMapping, $anoOrigemModel->id, $anoDestinoModel->id);
            }

            // 4. Transferir coordenadores (opcional)
            if ($transferirCoordenadores) {
                $stats['coordenadores'] = $this->transferCoordenadores($anoOrigem, $anoDestino, $anoOrigemModel->id, $anoDestinoModel->id);
            }

            if ($transferirHorarios) {
                $stats['horarios'] = $this->transferHorarios($turmaMapping, $anoOrigemModel->id, $anoDestinoModel->id);
            }

            if ($transferirCalendario) {
                $stats['calendario_provas'] = $this->transferCalendarioProvas($turmaMapping, $anoOrigemModel->id, $anoDestinoModel->id);
            }

            DB::commit();

            return [
                'success' => true,
                'stats' => $stats,
                'turma_mapping' => $turmaMapping,
                'message' => $this->buildSuccessMessage($stats),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erro ao executar transferência: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Transfere turmas de um ano para outro
     * Retorna mapeamento: [turma_antiga_id => turma_nova_id]
     */
    private function transferTurmas(string $anoOrigem, string $anoDestino, int $anoOrigemId, int $anoDestinoId): array
    {
        $turmasOrigem = Turma::where('ano_lectivo_id', $anoOrigemId)->get();
        $mapping = [];
        $this->skippedTurmas = 0;

        foreach ($turmasOrigem as $turmaAntiga) {
            $existing = Turma::where('codigo', $turmaAntiga->codigo)
                ->where('ano_lectivo_id', $anoDestinoId)
                ->first();

            if ($existing) {
                $mapping[$turmaAntiga->id] = $existing->id;
                $this->skippedTurmas++;
                continue;
            }

            $turmaNova = Turma::create([
                'codigo' => $turmaAntiga->codigo,
                'nome' => $turmaAntiga->nome,
                'curso_id' => $turmaAntiga->curso_id,
                'classe_id' => $turmaAntiga->classe_id,
                'sala_id' => $turmaAntiga->sala_id,
                'turno_id' => $turmaAntiga->turno_id,
                'ano_lectivo' => $anoDestino,
                'ano_lectivo_id' => $anoDestinoId,
                'activo' => true,
            ]);

            $mapping[$turmaAntiga->id] = $turmaNova->id;
        }

        return $mapping;
    }

    /**
     * Transfere associações professor-disciplina-turma
     */
    private function transferAssociacoes(string $anoOrigem, string $anoDestino, array $turmaMapping, int $anoOrigemId, int $anoDestinoId): int
    {
        $associacoesOrigem = ProfessorDisciplinaTurma::where('ano_lectivo_id', $anoOrigemId)->get();
        $count = 0;

        foreach ($associacoesOrigem as $assocAntiga) {
            // Verificar se turma foi mapeada
            if (!isset($turmaMapping[$assocAntiga->turma_id])) {
                continue;
            }

            $novaTurmaId = $turmaMapping[$assocAntiga->turma_id];

            // Verificar se já existe (evitar duplicação)
            $existe = ProfessorDisciplinaTurma::where('user_id', $assocAntiga->user_id)
                ->where('disciplina_id', $assocAntiga->disciplina_id)
                ->where('turma_id', $novaTurmaId)
                ->where('ano_lectivo_id', $anoDestinoId)
                ->exists();

            if ($existe) {
                continue; // Saltar duplicados
            }

            // Criar nova associação
            ProfessorDisciplinaTurma::create([
                'user_id' => $assocAntiga->user_id,
                'disciplina_id' => $assocAntiga->disciplina_id,
                'turma_id' => $novaTurmaId,
                'ano_lectivo' => $anoDestino,
                'ano_lectivo_id' => $anoDestinoId,
                'activo' => true,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Transfere directores de turma
     */
    private function transferDirectores(string $anoOrigem, string $anoDestino, array $turmaMapping, int $anoOrigemId, int $anoDestinoId): int
    {
        $directoresOrigem = DirectorTurma::where('ano_lectivo_id', $anoOrigemId)->get();
        $count = 0;

        foreach ($directoresOrigem as $directorAntigo) {
            // Verificar se turma foi mapeada
            if (!isset($turmaMapping[$directorAntigo->turma_id])) {
                continue;
            }

            $novaTurmaId = $turmaMapping[$directorAntigo->turma_id];

            // Verificar se já existe
            $existe = DirectorTurma::where('user_id', $directorAntigo->user_id)
                ->where('turma_id', $novaTurmaId)
                ->where('ano_lectivo_id', $anoDestinoId)
                ->exists();

            if ($existe) {
                continue;
            }

            // Criar novo director
            DirectorTurma::create([
                'user_id' => $directorAntigo->user_id,
                'turma_id' => $novaTurmaId,
                'ano_lectivo' => $anoDestino,
                'ano_lectivo_id' => $anoDestinoId,
                'activo' => true,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Transfere coordenadores de curso
     */
    private function transferCoordenadores(string $anoOrigem, string $anoDestino, int $anoOrigemId, int $anoDestinoId): int
    {
        $coordenadoresOrigem = CoordenadorCurso::where('ano_lectivo_id', $anoOrigemId)->get();
        $count = 0;

        foreach ($coordenadoresOrigem as $coordAntigo) {
            // Verificar se já existe
            $existe = CoordenadorCurso::where('user_id', $coordAntigo->user_id)
                ->where('curso_id', $coordAntigo->curso_id)
                ->where('ano_lectivo_id', $anoDestinoId)
                ->exists();

            if ($existe) {
                continue;
            }

            // Criar novo coordenador
            CoordenadorCurso::create([
                'user_id' => $coordAntigo->user_id,
                'curso_id' => $coordAntigo->curso_id,
                'ano_lectivo' => $anoDestino,
                'ano_lectivo_id' => $anoDestinoId,
                'activo' => true,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Copia grelha de horários e configuração por turma mapeada.
     */
    private function transferHorarios(array $turmaMapping, int $anoOrigemId, int $anoDestinoId): int
    {
        $count = 0;

        foreach ($turmaMapping as $turmaAntigaId => $turmaNovaId) {
            $config = TurmaHorarioConfig::where('turma_id', $turmaAntigaId)
                ->where('ano_lectivo_id', $anoOrigemId)
                ->first();

            if ($config) {
                TurmaHorarioConfig::updateOrCreate(
                    ['turma_id' => $turmaNovaId, 'ano_lectivo_id' => $anoDestinoId],
                    $config->only([
                        'intervalo_activo',
                        'intervalo_apos_periodo',
                        'intervalo_duracao_minutos',
                        'intervalo_texto',
                    ])
                );
            }

            $slots = TurmaHorario::where('turma_id', $turmaAntigaId)
                ->where('ano_lectivo_id', $anoOrigemId)
                ->get();

            foreach ($slots as $slot) {
                $existe = TurmaHorario::where('turma_id', $turmaNovaId)
                    ->where('ano_lectivo_id', $anoDestinoId)
                    ->where('dia_semana', $slot->dia_semana)
                    ->where('hora_inicio', $slot->hora_inicio)
                    ->exists();

                if ($existe) {
                    continue;
                }

                TurmaHorario::create([
                    'turma_id' => $turmaNovaId,
                    'disciplina_id' => $slot->disciplina_id,
                    'professor_id' => $slot->professor_id,
                    'sala_id' => $slot->sala_id,
                    'ano_lectivo_id' => $anoDestinoId,
                    'dia_semana' => $slot->dia_semana,
                    'hora_inicio' => $slot->hora_inicio,
                    'hora_fim' => $slot->hora_fim,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Copia calendário de provas e configuração por turma mapeada.
     */
    private function transferCalendarioProvas(array $turmaMapping, int $anoOrigemId, int $anoDestinoId): int
    {
        $count = 0;

        foreach ($turmaMapping as $turmaAntigaId => $turmaNovaId) {
            $config = TurmaCalendarioProvaConfig::where('turma_id', $turmaAntigaId)
                ->where('ano_lectivo_id', $anoOrigemId)
                ->first();

            if ($config) {
                TurmaCalendarioProvaConfig::updateOrCreate(
                    ['turma_id' => $turmaNovaId, 'ano_lectivo_id' => $anoDestinoId],
                    $config->only([
                        'hora_1a_inicio',
                        'hora_1a_fim',
                        'hora_2a_inicio',
                        'hora_2a_fim',
                    ])
                );
            }

            $provas = CalendarioProva::where('turma_id', $turmaAntigaId)
                ->where('ano_lectivo_id', $anoOrigemId)
                ->get();

            foreach ($provas as $prova) {
                $existe = CalendarioProva::where('turma_id', $turmaNovaId)
                    ->where('ano_lectivo_id', $anoDestinoId)
                    ->where('data_prova', $prova->data_prova)
                    ->where('slot', $prova->slot)
                    ->where('disciplina_id', $prova->disciplina_id)
                    ->exists();

                if ($existe) {
                    continue;
                }

                CalendarioProva::create([
                    'turma_id' => $turmaNovaId,
                    'disciplina_id' => $prova->disciplina_id,
                    'ano_lectivo_id' => $anoDestinoId,
                    'data_prova' => $prova->data_prova,
                    'slot' => $prova->slot,
                    'hora_inicio' => $prova->hora_inicio,
                    'hora_fim' => $prova->hora_fim,
                    'tipo' => $prova->tipo,
                    'periodo' => $prova->periodo,
                    'observacoes' => $prova->observacoes,
                    'supervisor_nome' => $prova->supervisor_nome,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Constrói mensagem de sucesso
     */
    private function buildSuccessMessage(array $stats): string
    {
        $parts = [];

        if ($stats['turmas'] > 0) {
            $parts[] = "{$stats['turmas']} turma(s)";
        }

        if ($stats['associacoes'] > 0) {
            $parts[] = "{$stats['associacoes']} associação(ões)";
        }

        if ($stats['directores'] > 0) {
            $parts[] = "{$stats['directores']} director(es)";
        }

        if ($stats['coordenadores'] > 0) {
            $parts[] = "{$stats['coordenadores']} coordenador(es)";
        }

        if (($stats['horarios'] ?? 0) > 0) {
            $parts[] = "{$stats['horarios']} slot(s) de horário";
        }

        if (($stats['calendario_provas'] ?? 0) > 0) {
            $parts[] = "{$stats['calendario_provas']} prova(s) no calendário";
        }

        $message = 'Transferência concluída com sucesso: ' . implode(', ', $parts) . '.';

        return $message;
    }
}
