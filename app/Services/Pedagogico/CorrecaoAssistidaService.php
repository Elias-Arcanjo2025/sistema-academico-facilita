<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedCorrecao;
use App\Models\Pedagogico\PedCorrecaoItem;
use App\Models\Pedagogico\PedProva;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\PromptBuilder;
use App\Services\OCR\OCRServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CorrecaoAssistidaService
{
    public function __construct(
        protected OCRServiceInterface $ocrService,
        protected AIServiceInterface $aiService,
        protected PromptBuilder $promptBuilder,
        protected IaGenerationLogger $iaLogger,
        protected ChaveCorrecaoService $chaveCorrecaoService,
    ) {}

    public function processar(
        PedProva $prova,
        UploadedFile $ficheiro,
        User $professor,
        ?int $alunoId = null,
    ): PedCorrecao {
        $prova->loadMissing('chaveCorrecao');

        $chave = $this->chaveCorrecaoService->construir($prova);
        $prova->setRelation('chaveCorrecao', $chave);

        $ocrResult = $this->ocrService->extractText($ficheiro);
        $ficheiroPath = $this->guardarFicheiro($ficheiro);

        $geracao = $this->iaLogger->iniciar($professor, 'correcao', [
            'prova_id' => $prova->id,
            'aluno_id' => $alunoId,
        ]);

        try {
            $questoes = $prova->conteudo_json['questoes'] ?? [];

            $prompt = $this->promptBuilder->render('correcao', [
                'ocr_texto' => $ocrResult->texto,
                'questoes_json' => json_encode($questoes, JSON_UNESCAPED_UNICODE),
                'chave_json' => json_encode($chave->respostas_json, JSON_UNESCAPED_UNICODE),
            ]);

            $resultado = $this->aiService->chatJson(
                [
                    ['role' => 'system', 'content' => $this->promptBuilder->systemPedagogico()],
                    ['role' => 'user', 'content' => $prompt],
                ],
                [],
                [
                    'model' => config('pedagogico.models.correcao'),
                    'max_tokens' => (int) config('pedagogico.prova_max_tokens', 4096),
                ]
            );

            $data = $resultado['data'] ?? $resultado;
            $this->iaLogger->concluir($geracao, $resultado['meta'] ?? [], $data);

            $itensIa = $this->indexarItensIa($data['itens'] ?? []);

            return DB::transaction(function () use ($prova, $professor, $alunoId, $ficheiroPath, $ocrResult, $chave, $data, $itensIa) {
                $correcao = PedCorrecao::create([
                    'prova_id' => $prova->id,
                    'aluno_id' => $alunoId,
                    'professor_id' => $professor->id,
                    'ficheiro_path' => $ficheiroPath,
                    'ocr_provider' => $ocrResult->provider,
                    'ocr_texto' => $ocrResult->texto,
                    'ocr_confianca_global' => $ocrResult->confiancaGlobal,
                    'sugestao_nota' => $data['sugestao_nota_total'] ?? null,
                    'estado' => 'pendente',
                ]);

                foreach ($chave->respostas_json as $ref => $entrada) {
                    $ref = (string) $ref;
                    $itemIa = $itensIa->get($ref, []);
                    $extraida = trim((string) ($itemIa['resposta_extraida'] ?? ''));
                    $sugestaoPontos = isset($itemIa['sugestao_pontos'])
                        ? (float) $itemIa['sugestao_pontos']
                        : $this->calcularPontosSugeridos($ref, $extraida, $chave);

                    PedCorrecaoItem::create([
                        'correcao_id' => $correcao->id,
                        'questao_ref' => $ref,
                        'resposta_extraida' => $extraida !== '' ? $extraida : null,
                        'resposta_esperada' => $this->formatarRespostaEsperada($entrada),
                        'confianca' => $this->normalizarConfianca($itemIa['confianca'] ?? ($extraida === '' ? 'baixa' : 'media')),
                        'sugestao_pontos' => $sugestaoPontos,
                    ]);
                }

                if (! isset($data['sugestao_nota_total'])) {
                    $correcao->update([
                        'sugestao_nota' => $correcao->itens()->sum('sugestao_pontos'),
                    ]);
                }

                return $correcao->fresh('itens');
            });
        } catch (\Throwable $e) {
            $this->iaLogger->falhar($geracao, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @return Collection<string, array<string, mixed>>
     */
    protected function indexarItensIa(array $itens): Collection
    {
        return collect($itens)
            ->map(function (array $item) {
                $ref = (string) ($item['questao_ref'] ?? '');
                $ref = preg_replace('/\D/', '', $ref) ?: $ref;

                return array_merge($item, ['questao_ref' => $ref]);
            })
            ->filter(fn (array $item) => ($item['questao_ref'] ?? '') !== '')
            ->keyBy('questao_ref');
    }

    protected function formatarRespostaEsperada(array $entrada): ?string
    {
        $texto = $entrada['resposta'] ?? null;
        if (is_string($texto) && trim($texto) !== '') {
            return trim($texto);
        }

        $letra = $entrada['resposta_letra'] ?? null;
        if (is_string($letra) && $letra !== '') {
            return 'Opção '.$letra;
        }

        return null;
    }

    protected function guardarFicheiro(UploadedFile $ficheiro): string
    {
        $disk = config('pedagogico.ocr.storage_disk', 'local');
        $basePath = config('pedagogico.ocr.storage_path', 'pedagogico/correcoes');
        $nome = Str::uuid().'.'.$ficheiro->getClientOriginalExtension();

        return Storage::disk($disk)->putFileAs($basePath, $ficheiro, $nome);
    }

    protected function calcularPontosSugeridos(string $ref, string $respostaExtraida, object $chave): float
    {
        $entrada = $chave->respostas_json[$ref] ?? null;
        if (! $entrada) {
            return 0.0;
        }

        $cotacao = (float) ($entrada['cotacao'] ?? 0);
        $extraida = trim($respostaExtraida);

        if ($extraida === '') {
            return 0.0;
        }

        $esperada = trim((string) ($entrada['resposta'] ?? ''));
        $tipo = Str::lower((string) ($entrada['tipo'] ?? ''));

        if ($tipo === 'objectiva') {
            $letraEsperada = Str::upper((string) ($entrada['resposta_letra'] ?? 'A'));
            $letraExtraida = Str::upper(Str::ascii(substr($extraida, 0, 1)));

            if (preg_match('/^[A-D]$/', $letraExtraida) && $letraExtraida === $letraEsperada) {
                return $cotacao;
            }

            if ($esperada !== '' && $this->textosSemelhantes($extraida, $esperada)) {
                return $cotacao;
            }

            return 0.0;
        }

        if ($esperada === '') {
            return 0.0;
        }

        if ($this->textosSemelhantes($extraida, $esperada)) {
            return $cotacao;
        }

        $similaridade = similar_text(
            Str::lower($extraida),
            Str::lower($esperada),
            $percentagem
        );

        if ($similaridade && $percentagem >= 55) {
            return round($cotacao * ($percentagem / 100), 2);
        }

        return 0.0;
    }

    protected function textosSemelhantes(string $a, string $b): bool
    {
        return Str::lower(trim($a)) === Str::lower(trim($b));
    }

    protected function normalizarConfianca(mixed $confianca): string
    {
        if (is_numeric($confianca)) {
            $n = (float) $confianca;
            if ($n <= 1) {
                $n *= 100;
            }

            return match (true) {
                $n >= 75 => 'alta',
                $n >= 45 => 'media',
                default => 'baixa',
            };
        }

        $valor = Str::ascii(Str::lower(trim((string) $confianca)));

        if ($valor === '') {
            return 'media';
        }

        if (in_array($valor, ['alta', 'media', 'baixa'], true)) {
            return $valor;
        }

        if (str_contains($valor, 'alt') || str_contains($valor, 'high')) {
            return 'alta';
        }

        if (str_contains($valor, 'baix') || str_contains($valor, 'low')) {
            return 'baixa';
        }

        if (str_contains($valor, 'medi') || str_contains($valor, 'medium')) {
            return 'media';
        }

        return 'media';
    }
}
