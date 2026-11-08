<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\MaterialAcademico;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\PromptBuilder;
use App\Services\MaterialAcademicoService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gera fichas / materiais de apoio via IA e grava em Materiais académicos.
 */
class MaterialApoioIaService
{
    public function __construct(
        protected AIServiceInterface $aiService,
        protected PromptBuilder $promptBuilder,
        protected IaGenerationLogger $iaLogger,
        protected DisciplinaCoordEscopoService $escopo,
        protected MaterialAcademicoService $materiais,
    ) {}

    public function podeGerar(User $user, int $disciplinaId): bool
    {
        if (! $user->can('pedagogico.ia.gerar') || ! $user->can('materiais.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        return $this->escopo->coordena($user, $disciplinaId);
    }

    /**
     * @param  array{disciplina_id:int, titulo?:string, tema:string, tipo?:string, classe?:string, ano_lectivo_id?:int}  $dados
     */
    public function gerar(User $user, array $dados): MaterialAcademico
    {
        $disciplinaId = (int) $dados['disciplina_id'];
        abort_unless($this->podeGerar($user, $disciplinaId), 403);

        $disciplina = Disciplina::findOrFail($disciplinaId);
        $tipo = $dados['tipo'] ?? 'ficha_apoio';
        $tema = $dados['tema'];
        $classe = $dados['classe'] ?? '';
        $anoId = (int) ($dados['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId());

        $contexto = [
            'tipo' => $tipo,
            'disciplina' => $disciplina->nome,
            'classe' => $classe,
            'tema' => $tema,
        ];

        $geracao = $this->iaLogger->iniciar($user, 'material_apoio', $contexto);

        try {
            $prompt = $this->promptBuilder->render('material_apoio', $contexto);
            $resultado = $this->aiService->chatJson(
                [
                    ['role' => 'system', 'content' => $this->promptBuilder->systemPedagogico()],
                    ['role' => 'user', 'content' => $prompt],
                ],
                [],
                ['model' => config('pedagogico.models.exercicio')]
            );

            $conteudo = $resultado['data'] ?? $resultado;
            $this->iaLogger->concluir($geracao, $resultado['meta'] ?? [], $conteudo);

            $titulo = $dados['titulo']
                ?? ($conteudo['titulo'] ?? ('Material de apoio — '.$tema));

            $texto = $this->formatarTexto($conteudo);
            $slug = Str::slug(Str::limit($titulo, 60, '')) ?: 'material-apoio';
            $path = "materiais/{$anoId}/{$slug}-".Str::random(6).'.txt';
            Storage::disk('local')->put($path, $texto);

            return MaterialAcademico::create([
                'titulo' => Str::limit($titulo, 200, ''),
                'descricao' => 'Gerado por IA ('.$tipo.'). Tema: '.$tema,
                'ficheiro_path' => $path,
                'mime' => 'text/plain',
                'tamanho' => strlen($texto),
                'disciplina_id' => $disciplinaId,
                'turma_id' => null,
                'ano_lectivo_id' => $anoId,
                'publicado' => true,
                'criado_por' => $user->id,
            ]);
        } catch (\Throwable $e) {
            $this->iaLogger->falhar($geracao, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $conteudo
     */
    private function formatarTexto(array $conteudo): string
    {
        $linhas = [];
        $linhas[] = $conteudo['titulo'] ?? 'Material de apoio';
        $linhas[] = str_repeat('=', 40);
        $linhas[] = '';

        if (! empty($conteudo['objectivos'])) {
            $linhas[] = 'Objectivos:';
            foreach ((array) $conteudo['objectivos'] as $o) {
                $linhas[] = '- '.$o;
            }
            $linhas[] = '';
        }

        if (! empty($conteudo['conteudo'])) {
            $linhas[] = is_string($conteudo['conteudo'])
                ? $conteudo['conteudo']
                : json_encode($conteudo['conteudo'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $linhas[] = '';
        }

        if (! empty($conteudo['actividades'])) {
            $linhas[] = 'Actividades:';
            foreach ((array) $conteudo['actividades'] as $i => $a) {
                $linhas[] = ($i + 1).'. '.(is_string($a) ? $a : ($a['texto'] ?? json_encode($a, JSON_UNESCAPED_UNICODE)));
            }
            $linhas[] = '';
        }

        if (! empty($conteudo['exercicios'])) {
            $linhas[] = 'Exercícios / questões:';
            foreach ((array) $conteudo['exercicios'] as $i => $ex) {
                $texto = is_string($ex) ? $ex : ($ex['texto'] ?? json_encode($ex, JSON_UNESCAPED_UNICODE));
                $linhas[] = ($i + 1).'. '.$texto;
            }
        }

        return implode("\n", $linhas);
    }
}
