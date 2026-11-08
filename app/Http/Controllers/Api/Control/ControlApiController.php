<?php

namespace App\Http\Controllers\Api\Control;

use App\Http\Controllers\Controller;
use App\Models\Control\ControlEscola;
use App\Services\Control\ControlHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlApiController extends Controller
{
    public function __construct(
        private readonly ControlHubService $hubService,
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var ControlEscola $escola */
        $escola = $request->attributes->get('control_escola');

        $data = $request->validate([
            'instituicao' => ['nullable', 'array'],
            'instituicao.nome' => ['nullable', 'string', 'max:255'],
            'instituicao.email' => ['nullable', 'email', 'max:255'],
            'instituicao.telefone' => ['nullable', 'string', 'max:50'],
            'app_url' => ['nullable', 'string', 'max:500'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'ano_lectivo' => ['nullable', 'string', 'max:32'],
            'metricas' => ['nullable', 'array'],
            'saude' => ['nullable', 'array'],
            'reported_at' => ['nullable', 'string'],
        ]);

        $license = $this->hubService->processHeartbeat($escola, $data);

        return response()->json($license);
    }

    public function syncAlunos(Request $request): JsonResponse
    {
        /** @var ControlEscola $escola */
        $escola = $request->attributes->get('control_escola');

        $data = $request->validate([
            'ano_lectivo' => ['nullable', 'string', 'max:32'],
            'alunos' => ['required', 'array'],
            'alunos.*.aluno_uuid' => ['required', 'uuid'],
            'alunos.*.aluno_local_id' => ['nullable', 'integer'],
            'alunos.*.numero_processo' => ['nullable', 'string', 'max:100'],
            'alunos.*.nome' => ['required', 'string', 'max:255'],
            'alunos.*.genero' => ['nullable', 'string', 'max:20'],
            'alunos.*.ano_lectivo' => ['nullable', 'string', 'max:32'],
            'alunos.*.turma_codigo' => ['nullable', 'string', 'max:50'],
            'alunos.*.turma_nome' => ['nullable', 'string', 'max:255'],
            'alunos.*.curso_nome' => ['nullable', 'string', 'max:255'],
            'alunos.*.classe_nome' => ['nullable', 'string', 'max:100'],
            'alunos.*.situacao_academica' => ['nullable', 'string', 'max:40'],
            'alunos.*.activo' => ['nullable', 'boolean'],
            'reported_at' => ['nullable', 'string'],
        ]);

        $result = $this->hubService->syncAlunos($escola, $data);

        return response()->json($result);
    }

    public function license(Request $request): JsonResponse
    {
        /** @var ControlEscola $escola */
        $escola = $request->attributes->get('control_escola');

        return response()->json($this->hubService->licensePayload($escola));
    }

    /**
     * Endpoint na escola: o hub pede sync imediato.
     */
    public function runSync(Request $request): JsonResponse
    {
        if (config('control.mode') !== 'school') {
            return response()->json(['message' => 'Só disponível em modo school.'], 503);
        }

        $licenseService = app(\App\Services\Control\ControlLicenseService::class);
        $state = $licenseService->refreshFromHub(withSync: true);

        return response()->json([
            'ok' => true,
            'status' => $state->status,
            'message' => $state->message,
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
