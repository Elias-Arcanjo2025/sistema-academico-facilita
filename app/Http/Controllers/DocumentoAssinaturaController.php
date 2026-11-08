<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Services\DocumentoAssinaturaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentoAssinaturaController extends Controller
{
    public function __construct(
        private DocumentoAssinaturaService $assinaturas,
    ) {
        $this->middleware('auth');
    }

    public function assinarBoletim(Request $request, Aluno $aluno): RedirectResponse
    {
        $this->authorize('view', $aluno);

        $data = $request->validate([
            'cargo' => ['required', 'string', 'max:64'],
            'ano_lectivo' => ['nullable', 'string', 'max:20'],
            'trimestre' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        $ano = $data['ano_lectivo'] ?? \App\Models\Configuracao::getAnoLectivoActual();
        $trimestre = isset($data['trimestre']) ? (int) $data['trimestre'] : null;
        $chave = 'aluno:'.$aluno->id.'|ano:'.$ano.'|t:'.($trimestre ?? 'anual');
        $hash = $this->assinaturas->hashBoletim($aluno->id, $ano, $trimestre);

        $this->assinaturas->assinar(
            $request->user(),
            'boletim',
            $chave,
            $hash,
            $data['cargo'],
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('success', 'Boletim assinado digitalmente com sucesso.');
    }

    public function assinarDocumentoAcademico(Request $request, Aluno $aluno): RedirectResponse
    {
        $this->authorize('view', $aluno);

        $data = $request->validate([
            'cargo' => ['required', 'string', 'max:64'],
            'tipo_documento' => ['required', 'string', 'in:frequencia,matricula,comprovativo,ficha'],
            'ano_lectivo' => ['nullable', 'string', 'max:20'],
        ]);

        $ano = $data['ano_lectivo'] ?? \App\Models\Configuracao::getAnoLectivoActual();
        $chave = $data['tipo_documento'].'|aluno:'.$aluno->id.'|ano:'.$ano;
        $hash = $this->assinaturas->hashDocumentoAcademico($data['tipo_documento'], $aluno->id, $ano);

        $this->assinaturas->assinar(
            $request->user(),
            'documento_academico',
            $chave,
            $hash,
            $data['cargo'],
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('success', 'Documento assinado digitalmente com sucesso.');
    }
}
